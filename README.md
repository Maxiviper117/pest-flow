# Pest Flow

Pest Flow adds behaviour-driven structure to Pest 5 tests. Organize tests as Features, Rules, and Scenarios, then describe each scenario with Given, When, and Then steps in PHP.

Every scenario remains a normal Pest test. Pest Flow records the behaviour hierarchy and execution details. Pest still discovers and runs tests, applies hooks, evaluates expectations, and reports failures.

## Contents

- [What Pest Flow adds](#what-pest-flow-adds)
- [Requirements](#requirements)
- [Install](#install)
- [Write and run a scenario](#write-and-run-a-scenario)
- [Generate reports](#generate-reports)
- [Organize and filter scenarios](#organize-and-filter-scenarios)
- [How scenarios run](#how-scenarios-run)
- [Inspect the registry](#inspect-the-registry)
- [Failures and skips](#failures-and-skips)
- [Scope and limitations](#scope-and-limitations)
- [Examples and documentation](#examples-and-documentation)
- [Development](#development)

## What Pest Flow adds

| Term | Purpose | Pest construct |
| --- | --- | --- |
| Feature | Names a user-visible capability or domain area. | `describe()` group |
| Rule | Names a business constraint within a feature. | Nested `describe()` group |
| Scenario | Gives one concrete example of a rule. | Normal Pest `it()` test |
| Given | Describes the starting state. | Recorded step callback |
| When | Performs the action under test. | Recorded step callback |
| Then | Checks the outcome, usually with a Pest expectation. | Recorded step callback |

A scenario uses ordinary PHP, Pest expectations, fixtures, and lifecycle hooks. Pest Flow does not require Gherkin files, a parser, reusable text-matched step definitions, or another test runner. You can adopt it a few scenarios at a time. A standalone `scenario()` does not need a Feature or Rule.

## Requirements

- PHP 8.4 or newer
- Pest 5
- Composer

## Install

Install Pest Flow as a development dependency from Packagist:

```sh
composer require --dev maxiviper117/pest-flow
```

Composer loads Pest Flow's functions automatically. You do not need a plugin bootstrap file. Keep Pest Flow in `require-dev` because its API is for writing tests.

## Write and run a scenario

Create `tests/Feature/InvoiceApprovalTest.php`:

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

Run the test with Pest:

```sh
vendor/bin/pest tests/Feature/InvoiceApprovalTest.php
```

Pest reports the scenario as one test. Add another scenario to cover another outcome. See the [runnable examples](examples/README.md) for more complete cases.

## Generate reports

Pest Flow can add a terminal report, export the behaviour tree as JSON, or generate static HTML documentation. Each report uses registry data from the current Pest process.

### Terminal report

Print the Feature, Rule, Scenario, and step hierarchy after Pest's normal output:

```sh
vendor/bin/pest --flow
```

The report includes scenario and step status. Pest still determines the command's exit status. The registry is process-local, so the report cannot combine data from `--parallel` workers.

### JSON export

Write only JSON to standard output with `--flow-json`:

```sh
vendor/bin/pest --flow-json
```

Pest's normal output is suppressed so standard output contains valid JSON. Pest's test exit code is preserved.

Write the JSON report to a file while keeping Pest's normal output:

```sh
vendor/bin/pest --flow-json=build/flow.json
```

The output directory must already exist. Schema version 1 includes Features, Rules, Scenarios, standalone scenarios, steps, IDs, source locations, direct tags, status, and duration. JSON export is unavailable with `--parallel`. See the [JSON export reference](docs/json-export.mdx) for the full schema.

### Living documentation

Generate a self-contained interactive behaviour viewer after Pest runs the tests:

```sh
vendor/bin/pest --flow-report
```

Pest writes `build/pest-flow/index.html` by default. It creates the output directory when needed.
Open the file in a browser or publish it as a static site.

To choose another output directory, pass it after `=`:

```sh
vendor/bin/pest --flow-report=build/behaviour
```

The viewer includes search across feature, rule, scenario, step, and tag text; filters for status,
tag, feature, rule, and source file; an expandable Given/When/Then scenario flow; source-location
copy controls; suite counts; and execution data when available. It has a light and dark theme
toggle. The HTML file needs no server or external assets.

The report is unavailable with `--parallel` because worker registries are process-local. See the
[living documentation guide](docs/living-documentation.mdx).

## Organize and filter scenarios

Add tags to Features, Rules, or Scenarios. Parent tags apply to descendant scenarios when filtering. JSON tag arrays contain only tags declared directly on each node.

```php
feature('Checkout', function (): void {
    rule('Payment', function (): void {
        scenario('charges a customer', function (): void {
            // Given / When / Then steps...
        })->tags('payments', 'critical');
    })->tags('billing');
})->tags('checkout');
```

Use Pest's `--group` option to select scenarios by tag:

```sh
vendor/bin/pest --group=payments
```

See [Tags and filtering](docs/tags.mdx) for validation rules and more examples.

## How scenarios run

When Pest loads a test file, Pest Flow registers its Feature, Rule, and Scenario nodes. When Pest runs a scenario, Pest Flow collects the step declarations, then calls their callbacks in declaration order. All step callbacks share the same Pest test object through `$this`.

Keep the scenario callback focused on declaring steps. Code outside step callbacks runs during collection, before the steps run. Put setup and actions inside the matching Given, When, or Then callback.

Feature and Rule callbacks define Pest groups. Put shared setup in `beforeEach()` at the group where it applies. Use non-static closures when a callback needs `$this`. Step names describe intent. Pest Flow does not require a fixed number or order of step types.

Each scenario remains one Pest test. Steps are not separate Pest tests or separately reported test cases. Step status and timing are available through the registry.

## Inspect the registry

`Pest\Flow\FlowRegistry` exposes the behaviour tree to PHP code in the current test process:

```php
use Pest\Flow\FlowRegistry;

$features = FlowRegistry::features();
$rules = FlowRegistry::rules();
$scenarios = FlowRegistry::scenarios();
$steps = FlowRegistry::steps();
```

Each list preserves declaration order. Feature, Rule, and Scenario nodes provide names, IDs, source locations, and child accessors. Scenario and Step nodes also provide execution data:

| Field | Meaning |
| --- | --- |
| `status` | `pending`, `running`, `passed`, `failed`, or `skipped`. |
| `duration` | Execution time in seconds. It is `null` until execution completes. |
| `exception` | The `Throwable` stored when a scenario or active step fails or raises a Pest skip exception. Later steps skipped after a failure have no exception. |
| `source` | The PHP file and line for a behaviour node or step declaration. |

Step nodes also record their type and description. Their source location points to the `given()`, `when()`, or `then()` call. Pest Flow builds IDs from node names and parent paths. Duplicate sibling names receive numeric suffixes.

Renaming a node can change its ID. Treat IDs as identifiers for the current behaviour tree, not as permanent identifiers across versions.

The registry supports in-process assertions and custom PHP tooling. It does not combine data across separate test commands. See the [registry reference](docs/registry.mdx) for node fields and registry timing.

## Failures and skips

If a step throws an exception or a Pest expectation fails, Pest Flow records the exception and duration on that step. It marks later declared steps as `skipped` without running their callbacks. Pest Flow rethrows the same exception, so Pest reports the scenario failure through its normal runner.

If a step throws a Pest skip exception, Pest Flow marks the active step and scenario as `skipped`. These rules let tools inspect step-level results while preserving Pest's normal pass, failure, and skip behavior.

## Scope and limitations

Pest Flow suits teams that use Pest 5 and want readable behaviour groupings, step-level metadata, or structured data for tools. Keep ordinary Pest tests where the behaviour hierarchy adds no value. Choose a Gherkin-based tool if scenarios must use `.feature` files or test authors cannot write PHP.

Current limitations:

- Given, When, and Then are labels. Pest Flow does not validate their order or require assertions in Then steps.
- Steps belong to one Pest test. Pest does not report each step as a separate test.
- Pest Flow does not parse Gherkin or map text labels to reusable step definitions.
- Registry-backed reports use the current process and cannot aggregate parallel workers.
- Pest Flow does not provide Laravel-specific helpers.
- Pest Flow is pre-1.0. Review release notes before upgrading because its API may change before the first major release.

Pest Flow itself does not depend on Laravel. You can use it in a Laravel project that uses Pest.

## Examples and documentation

- [Runnable examples](examples/README.md)
- [Quickstart](docs/quickstart.mdx)
- [Core concepts](docs/concepts.mdx)
- [DSL and API reference](docs/api.mdx)
- [Behaviour registry reference](docs/registry.mdx)
- [JSON export reference](docs/json-export.mdx)
- [Living documentation guide](docs/living-documentation.mdx)
- [First scenario walkthrough](docs/walkthroughs/first-scenario.mdx)
- [Hosted documentation](https://maxiviper117.github.io/pest-flow/)

## Development

Install development dependencies and run the PHP checks and test suite:

```sh
composer install
composer test
```

`composer test` runs the Rector dry-run check, Pint formatting, PHPStan at level 10, and the Pest
test suite. Run Composer metadata validation separately with `composer validate --strict`.

The static report's browser interactions are covered with Pest's Playwright-based browser plugin.
Install Chromium once, then run the report tests:

```sh
pnpm install --frozen-lockfile
pnpm exec playwright install chromium
composer test:browser
```

These tests run a Pest fixture to generate a real report, serve the static file locally, and exercise
the report in Chromium.

The documentation site uses pnpm and Node.js 22.12 or newer:

```sh
pnpm install --frozen-lockfile
pnpm run dev
pnpm run build
```
