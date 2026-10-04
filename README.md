# Pest Flow

Pest Flow adds Behavior-Driven Development (BDD) structure to Pest 5 tests. It lets a team express
application behaviour as **Features**, **Rules**, **Scenarios**, and **Given/When/Then** steps in
ordinary PHP.

Each scenario is still a normal Pest test. Pest remains responsible for discovering and running
tests, applying hooks, evaluating expectations, and reporting failures. Pest Flow adds a clear
behaviour hierarchy and an in-process registry with scenario and step execution metadata.

Use Pest Flow when you want your Pest suite to explain what the application should do, connect
business rules to runnable examples, and expose those examples as structured data for PHP tools.

## Contents

- [Why use BDD examples?](#why-use-bdd-examples)
- [What Pest Flow adds](#what-pest-flow-adds)
- [Requirements](#requirements)
- [Installation](#installation)
- [Write a scenario](#write-a-scenario)
- [How a scenario runs](#how-a-scenario-runs)
- [Inspect the behaviour registry](#inspect-the-behaviour-registry)
- [Console output](#console-output)
- [Failure and skip behaviour](#failure-and-skip-behaviour)
- [Scope and limitations](#scope-and-limitations)
- [Examples and documentation](#examples-and-documentation)
- [Development](#development)

## Why use BDD examples?

BDD uses concrete examples to describe the behaviour a system must provide. A scenario connects a
starting condition to an action and an observable result. That structure helps teams discuss
behaviour with the same business terms they use in requirements and code reviews.

Well-named scenarios can help a team:

- Make acceptance criteria executable instead of leaving them only in tickets or prose.
- Review business behaviour without first knowing every implementation detail.
- See which rule a test protects and what result the application must produce.
- Catch behaviour changes through the same test suite used during development and CI.
- Keep examples beside the application code and fixtures they exercise.

BDD does not make a test suite complete by itself. The suite describes the behaviour that the team
has chosen to encode as scenarios. Pest Flow gives those scenarios a consistent structure and
records their behaviour in PHP.

## What Pest Flow adds

Pest Flow provides a small set of PHP functions that map behaviour concepts to Pest constructs:

| Pest Flow term | Purpose | Pest construct |
| --- | --- | --- |
| Feature | Names a user-visible capability or domain area. | `describe()` group |
| Rule | Names a business constraint within a feature. | Nested `describe()` group |
| Scenario | Gives one concrete example of a rule. | Normal Pest `it()` test |
| Given | Describes the starting state. | Recorded step callback |
| When | Performs the action under test. | Recorded step callback |
| Then | Checks the outcome, usually with a Pest expectation. | Recorded step callback |

These functions use the existing Pest test lifecycle. You can use Pest expectations, PHP code,
fixtures, and lifecycle hooks. Feature and rule groups keep the behaviour suite organized, while
Pest continues to run and report each scenario.

Pest Flow does not require Gherkin files, a parser, string-matched step definitions, or another test
runner. The scenario is PHP code, so it can call the same application services and test helpers as
the rest of the suite. A project can also adopt Pest Flow a few scenarios at a time. A standalone
`scenario()` works without a Feature or Rule.

### When Pest Flow is a good fit

Choose Pest Flow when your team wants to:

- Use Pest 5 to describe business rules as concrete examples.
- Show reviewers the Feature, Rule, Scenario, and step structure in PHP.
- Query a behaviour tree and execution metadata from custom PHP tooling.
- Add BDD structure gradually without adding a second test runner.

Keep ordinary Pest tests where the behaviour hierarchy adds no value. Choose a Gherkin-based tool
when scenarios must use `.feature` files or when test authors cannot write PHP.

## Requirements

- PHP 8.4 or newer
- Pest 5
- Composer

## Installation

Pest Flow is not published on Packagist. Install it from its GitHub repository as a development
dependency:

```sh
composer config repositories.pest-flow vcs https://github.com/Maxiviper117/pest-flow
composer require --dev maxiviper117/pest-flow:dev-main
```

Composer loads the Pest Flow functions automatically. You do not need to add a plugin bootstrap
file. Keep Pest Flow in `require-dev` because its API is for tests, not production application code.

## Write a scenario

This example describes a rule for invoice approval. The scenario checks the outcome through the
same Pest `expect()` API used by ordinary Pest tests.

```php
<?php

declare(strict_types=1);

use function Pest\Flow\feature;
use function Pest\Flow\given;
use function Pest\Flow\rule;
use function Pest\Flow\scenario;
use function Pest\Flow\then;
use function Pest\Flow\when;

feature('Invoice approval', function (): void {
    rule('Invoices need a purchase order before approval', function (): void {
        scenario('holds an invoice without a purchase order', function (): void {
            given('a submitted invoice without a purchase order', function (): void {
                $this->invoice = [
                    'status' => 'submitted',
                    'purchaseOrder' => null,
                ];
            });

            when('the invoice is reviewed', function (): void {
                $this->invoice['status'] = 'needs-information';
            });

            then('the invoice is held for correction', function (): void {
                expect($this->invoice['status'])->toBe('needs-information');
            });
        });
    });
});
```

Save the file under a path Pest discovers, such as `tests/Feature/InvoiceApprovalTest.php`, then
run it:

```sh
vendor/bin/pest tests/Feature/InvoiceApprovalTest.php
```

Pest reports the scenario as one test. To cover another outcome, add another scenario under the
rule or place it under a different rule. See the [examples](examples/README.md) for full files that
cover multiple rules, Pest hooks, standalone scenarios, and execution metadata.

### Use Pest hooks and shared state

Feature and Rule callbacks define Pest groups. Put shared setup in `beforeEach()` at the group where
the setup applies. Use Given steps for state that belongs to one scenario. Given, When, and Then
callbacks share the same Pest test object through `$this`.

Use non-static closures when a callback needs `$this`. Pest Flow calls step callbacks in
declaration order. The Given/When/Then names describe intent. Pest Flow does not require a fixed
number or order of step types.

## How a scenario runs

When Pest loads a test file, Pest Flow registers Feature, Rule, and Scenario nodes. When Pest runs a
scenario, Pest Flow collects the step declarations first, then executes their callbacks in order.
All step callbacks use the same Pest test object.

Keep the scenario callback focused on declaring steps. Statements outside step callbacks run during
the collection phase, before any step callback executes. Put scenario setup and actions inside the
corresponding Given, When, or Then callback.

Each scenario remains one Pest test. Individual steps are not separate tests or separately reported
Pest test cases. Step status and timing are available through the registry described below.

## Inspect the behaviour registry

`Pest\Flow\FlowRegistry` exposes the behaviour tree to PHP code in the current test process:

```php
use Pest\Flow\FlowRegistry;

$features = FlowRegistry::features();
$rules = FlowRegistry::rules();
$scenarios = FlowRegistry::scenarios();
$steps = FlowRegistry::steps();
```

Each list preserves declaration order. Feature, Rule, and Scenario nodes provide names, path-like
IDs, source locations, and child accessors. Scenario and Step nodes also provide execution data:

| Field | Meaning |
| --- | --- |
| `status` | `pending`, `running`, `passed`, `failed`, or `skipped`. |
| `duration` | Execution time in seconds. It is `null` until execution completes. |
| `exception` | The `Throwable` that Pest Flow stores when a scenario or active step fails or raises a Pest skip exception. Later steps that Pest Flow skips after a failure have no exception. |
| `source` | The PHP file and line for a behaviour node or step declaration. |

Step nodes also record their type and description. Their source location points to the `given()`,
`when()`, or `then()` call. Pest Flow builds IDs from node names and parent paths. Duplicate sibling
names receive numeric suffixes to keep IDs unique.

Renaming a node can change its ID. Treat IDs as identifiers for the current behaviour tree, not as
permanent identifiers across versions.

The registry supports in-process assertions and custom PHP tooling. It is static and process-local;
it does not combine data across separate test commands.

The registry adds structure beyond a scenario title. A tool can follow a Feature to its Rules and
Scenarios, then inspect their steps, source locations, status, duration, and exceptions.
This supports focused checks and integrations without parsing the original PHP source.

See the [registry reference](docs/registry.mdx) for all node fields and registry timing.

## Console output

Run Pest with `--flow` to print the registered Feature, Rule, Scenario, and Given/When/Then
hierarchy after Pest's normal output:

```sh
vendor/bin/pest --flow
```

The report marks passed, failed, skipped, pending, and running scenarios and steps. Pest continues to
run the tests and determine the command's exit status. The report uses the registry in the current
process, so it does not aggregate results from `--parallel` workers. Status symbols are colored when
the terminal supports it; color is disabled automatically when output is redirected.

## JSON export

Use `--flow-json` to write a versioned JSON document to stdout. Pest's normal test output is
suppressed so stdout contains only JSON:

```sh
vendor/bin/pest --flow-json
```

Use `--flow-json=flow.json` to write the report to a file while keeping Pest's normal output:

```sh
vendor/bin/pest --flow-json=flow.json
```

Schema version 1 contains feature trees and standalone scenarios, with IDs, source locations,
execution status, durations, and tags declared on each feature, rule, and scenario. JSON export is
unavailable with `--parallel` because worker registries are process-local.
See the [JSON export reference](docs/json-export.mdx) for the schema.

## Tags and filtering

Add tags to features, rules, or scenarios. Tags on a feature or rule are inherited by its scenarios
for filtering; each node's JSON `tags` array contains tags declared directly on that node.

```php
feature('Checkout', function (): void {
    rule('Payment', function (): void {
        scenario('charges a customer', function (): void {
            // Given / When / Then steps...
        })->tags('payments', 'critical');
    })->tags('billing');
})->tags('checkout');
```

Pest Flow maps tags to Pest groups, so use Pest's normal `--group` option:

```sh
vendor/bin/pest --group=payments
```

See the [tags and filtering guide](docs/tags.mdx) for details.

## Failure and skip behaviour

If a step throws an exception or a Pest expectation fails, Pest Flow records the exception and
duration on that step. It marks later declared steps as `skipped` without running their callbacks.
Pest Flow rethrows the same exception, so Pest reports the scenario failure through its normal
runner.

If a step throws a Pest skip exception, Pest Flow marks the active step and scenario as `skipped`.
These rules let a tool inspect step-level results while preserving the normal Pest pass, failure,
and skip behaviour.

## Scope and limitations

Pest Flow is for teams that use Pest 5 and want behaviour examples written in PHP. It works well when
the team wants readable Feature/Rule/Scenario groupings, step-level metadata, and ordinary Pest
execution.

The current API is deliberately small:

- Given, When, and Then are labels. Pest Flow does not validate their order or require assertions
  in Then steps.
- A step is part of one Pest test for its scenario. Pest does not report each step as an independent test.
- Pest Flow does not parse Gherkin or map text labels to reusable step definitions.
- Registry data exists only in the current PHP process.
- Generated living documentation and Laravel-specific integration are not available yet.

Pest Flow itself does not depend on Laravel. You can use the core package in a Laravel project that
uses Pest, but Pest Flow does not provide Laravel-specific helpers.

Pest Flow is pre-1.0. Review release notes before upgrading because its API may change before the
first major release.

## Examples and documentation

- [Runnable examples](examples/README.md)
- [Quickstart](docs/quickstart.mdx)
- [Core concepts](docs/concepts.mdx)
- [DSL and API reference](docs/api.mdx)
- [Behaviour registry reference](docs/registry.mdx)
- [First scenario walkthrough](docs/walkthroughs/first-scenario.mdx)
- [Hosted documentation](https://maxiviper117.github.io/pest-flow/)

## Development

Run the PHP checks and test suite:

```sh
composer install
composer test
```

`composer test` runs the Rector dry-run check, Pint formatting, PHPStan at level 10, and the Pest test
suite. Run Composer metadata validation separately with `composer validate --strict`.

The documentation site uses pnpm and Node.js 22.12 or newer:

```sh
pnpm install --frozen-lockfile
pnpm run dev
pnpm run build
```
