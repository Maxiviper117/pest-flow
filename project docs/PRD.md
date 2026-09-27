# Pest Flow

## Product Requirements Document

### Status

Draft

### Implementation Status

The current implementation covers Milestones 0 and 1. Milestones 2 through 9 remain planned.

### Product

Pest Flow

### Type

Pest plugin for PHP

### Primary goal

Provide a lightweight, Pest-native Behaviour-Driven Development layer that lets developers define executable behaviour using:

- Feature
- Rule
- Scenario
- Given
- When
- Then

Pest remains responsible for test execution.

Pest Flow is responsible for representing, organizing, exposing, and reporting application behaviour.

---

# 1. Problem

Pest provides an excellent PHP testing experience, but it does not provide a structured model for describing application behaviour at the business or domain level.

Developers can write:

```php
it('activates a compliant contractor', function () {
    // ...
});
```

but the test itself does not explicitly represent:

```text
Feature
  Rule
    Scenario
      Given
      When
      Then
```

BDD frameworks such as Behat solve this through Gherkin, but introduce:

- another file format
- step-definition mapping
- additional tooling
- duplication between specifications and PHP
- weaker IDE navigation compared with native PHP
- additional cognitive overhead

Pest Flow should provide the behavioural structure of BDD while retaining native PHP and Pest.

---

# 2. Product Vision

Pest Flow turns a Pest test suite into an executable behavioural specification.

A developer should be able to write:

```php
feature('Contractor activation', function () {

    rule('Only compliant contractors can be activated', function () {

        scenario('Activate a compliant contractor', function () {

            given('a compliant contractor', function () {
                //
            });

            when('the contractor is approved', function () {
                //
            });

            then('the contractor becomes active', function () {
                //
            });

        });

    });

});
```

and Pest Flow should understand the structure as data:

```text
Feature
└── Rule
    └── Scenario
        ├── Given
        ├── When
        └── Then
```

That structure can eventually power:

- terminal output
- living documentation
- JSON exports
- CI reports
- behavioural coverage
- visualisation
- agent-readable specifications
- code navigation

---

# 3. Product Principles

## 3.1 Pest remains the test runner

Pest Flow must not replace Pest.

It should integrate with Pest's lifecycle and execution model rather than creating a parallel testing framework.

---

## 3.2 PHP is the specification language

No custom parser or `.feature` files should be required.

A Pest Flow specification should remain ordinary PHP.

---

## 3.3 Behaviour is structured data

The primary value of Pest Flow is not prettier syntax.

The following:

```php
given(...)
when(...)
then(...)
```

is only useful if Pest Flow captures these concepts into a structured behavioural model.

---

## 3.4 Minimal magic

A developer should be able to understand what is happening without learning a large framework.

The API should remain thin and predictable.

---

## 3.5 Progressive adoption

Projects should be able to introduce Pest Flow into an existing Pest suite gradually.

Existing Pest tests must continue working normally.

---

## 3.6 Framework agnostic

Pest Flow itself should not depend on Laravel.

Laravel integrations may exist separately if needed.

---

# 4. Target Users

Primary users:

- PHP developers using Pest
- Laravel developers
- teams using domain-driven design
- developers who want BDD without Gherkin
- teams using coding agents
- projects where business behaviour should be visible in the test suite

Secondary users:

- QA engineers
- product engineers
- technical product owners
- AI coding agents consuming structured behaviour metadata

---

# 5. Core Concepts

## Feature

Represents a user-visible or domain-level capability.

Example:

```text
Contractor Activation
```

---

## Rule

Represents a behavioural constraint or business rule.

Example:

```text
Only compliant contractors may be activated
```

A feature may contain multiple rules.

---

## Scenario

Represents one concrete example demonstrating a rule.

Example:

```text
Activate a fully compliant contractor
```

---

## Given

Describes initial state or context.

Example:

```text
Given a compliant contractor
```

---

## When

Describes an action or event.

Example:

```text
When the contractor is approved
```

---

## Then

Describes an expected outcome.

Example:

```text
Then the contractor becomes active
```

---

## And

Optional additional step that inherits the previous step type.

Example:

```php
given('a contractor exists', ...)
    ->and('their compliance checks are complete', ...);
```

This should not be required for MVP unless it can be implemented without complicating the underlying model.

---

# 6. Proposed API

## Basic syntax

```php
feature('Contractor activation', function () {

    rule('Only compliant contractors may activate', function () {

        scenario('Activate compliant contractor', function () {

            given('a compliant contractor', function () {
                $this->contractor =
                    Contractor::factory()->compliant()->create();
            });

            when('the contractor is approved', function () {
                $this->contractor->approve();
            });

            then('the contractor becomes active', function () {
                expect($this->contractor->status)
                    ->toBe(ContractorStatus::Active);
            });

        });

    });

});
```

---

# 7. Behaviour Model

Internally, Pest Flow should build a model independent of Pest's presentation layer.

Conceptually:

```php
Feature {
    id
    name
    description?
    tags[]
    rules[]
}

Rule {
    id
    name
    description?
    tags[]
    scenarios[]
}

Scenario {
    id
    name
    description?
    tags[]
    steps[]
    status
    duration
    source
}

Step {
    id
    type
    description
    status
    duration
    source
}
```

Possible step types:

```php
enum StepType
{
    case Given;
    case When;
    case Then;
}
```

The exact implementation should not be treated as public API initially.

---

# 8. Execution Model

Pest Flow should translate scenarios into Pest tests.

Conceptually:

```text
Pest Flow Scenario
        ↓
Pest Test
        ↓
PHPUnit
        ↓
Execution result
        ↓
Pest Flow result metadata
```

A scenario should behave as a single Pest test.

Steps should execute sequentially within that test.

The Milestone 1 implementation registers each scenario as an ordinary Pest test. When Pest runs
that test, Pest Flow records and executes each step in declaration order with the same Pest test
context. A failing step exception reaches Pest and prevents later steps from running. Per-step
execution states remain planned for Milestone 3.

If a step fails:

```text
Given ✓
Given ✓
When  ✓
Then  ✗
Then  skipped
```

Pest should still receive the underlying exception so its normal failure behaviour remains intact.

---

# 9. Runtime Context

Scenario steps need a shared context.

Example:

```php
given('a contractor', function () {
    $this->contractor = Contractor::factory()->create();
});

when('they are activated', function () {
    $this->contractor->activate();
});

then('they are active', function () {
    expect($this->contractor->isActive())->toBeTrue();
});
```

MVP can reuse Pest's existing test context if practical.

Pest Flow should avoid creating an unnecessary dependency injection container.

---

# 10. Tags

Tags should allow behaviour to be grouped.

Example:

```php
feature('Billing')
    ->tags('billing');

scenario('Charge customer')
    ->tags('critical', 'payments');
```

Potential use cases:

```bash
pest --flow-tag=payments
```

or:

```bash
pest --group=payments
```

Where possible, Pest Flow should map tags onto existing Pest/PHPUnit grouping mechanisms rather than invent a second filtering system.

---

# 11. Console Reporting

Pest Flow should eventually provide an optional behaviour-oriented terminal representation.

Example:

```text
Contractor Activation

  Rule: Only compliant contractors may activate

    ✓ Activate compliant contractor

      ✓ Given a compliant contractor
      ✓ When the contractor is approved
      ✓ Then the contractor becomes active

    ✗ Reject incomplete contractor

      ✓ Given an incomplete contractor
      ✓ When activation is attempted
      ✗ Then activation is rejected
```

This should complement normal Pest output rather than breaking it.

---

# 12. Machine-Readable Output

Pest Flow should provide structured output for tooling.

Example:

```bash
pest --flow-json
```

Possible output:

```json
{
  "features": [
    {
      "name": "Contractor activation",
      "rules": [
        {
          "name": "Only compliant contractors may activate",
          "scenarios": [
            {
              "name": "Activate compliant contractor",
              "status": "passed",
              "steps": [
                {
                  "type": "given",
                  "text": "a compliant contractor",
                  "status": "passed"
                }
              ]
            }
          ]
        }
      ]
    }
  ]
}
```

This becomes the foundation for external tooling.

---

# 13. Living Documentation

Pest Flow should eventually be able to generate static documentation from the behaviour model.

Possible command:

```bash
pest --flow-report
```

Output:

```text
build/pest-flow/index.html
```

The report should organize behaviour by:

```text
Feature
  Rule
    Scenario
      Steps
```

Initial reporting should focus on clarity rather than analytics.

---

# 14. Visualisation

A later Pest Flow viewer may represent behaviour visually.

Example:

```text
Contractor Management
        │
        ▼
Contractor Activation
        │
        ▼
Only compliant contractors activate
       / \
      /   \
success   failure
```

Potential later capabilities:

- feature explorer
- rule graph
- scenario explorer
- Given → When → Then flow
- filters
- tags
- source code links
- execution status
- historical execution results

This should be treated as a separate surface built on top of Pest Flow's JSON model.

---

# 15. Agent Support

A structured behaviour model is particularly useful for coding agents.

Potential command:

```bash
pest --flow-json
```

could allow an agent to query:

```text
What behaviours already exist for contractor activation?
```

or:

```text
Which rules apply before modifying this service?
```

A future command could expose behaviour without running tests:

```bash
pest --flow-list
```

or:

```bash
pest --flow-export
```

This enables agents to understand application expectations before modifying code.

---

# 16. Non-Goals

The initial versions should not attempt to:

- replace Pest
- replace PHPUnit
- replace Behat completely
- implement Gherkin parsing
- support `.feature` files
- provide browser automation
- implement its own mocking framework
- implement its own assertion library
- provide a Laravel-specific testing framework
- provide a hosted service
- provide test management
- provide AI functionality directly

Those can be considered separately if genuine demand appears.

---

# 17. Project Structure

Possible package structure:

```text
pest-flow/
├── src/
│   ├── FlowPlugin.php
│   │
│   ├── DSL/
│   │   ├── Feature.php
│   │   ├── Rule.php
│   │   ├── Scenario.php
│   │   └── Step.php
│   │
│   ├── Model/
│   │   ├── FeatureNode.php
│   │   ├── RuleNode.php
│   │   ├── ScenarioNode.php
│   │   └── StepNode.php
│   │
│   ├── Runtime/
│   │   ├── FlowRegistry.php
│   │   ├── ScenarioContext.php
│   │   └── ScenarioExecutor.php
│   │
│   ├── Reporting/
│   │   ├── JsonReporter.php
│   │   └── ConsoleReporter.php
│   │
│   └── Support/
│
├── tests/
├── composer.json
└── README.md
```

This structure is illustrative, not prescriptive.

---

# 18. Milestones

## Milestone 0: Technical Spike

### Status

Complete

### Goal

Prove that the core model integrates cleanly with Pest.

### Deliverables

Implement:

```php
scenario()
given()
when()
then()
```

Verify:

- [x] Scenario maps cleanly to a Pest test.
- [x] Pest lifecycle hooks continue working.
- [x] `$this` context works correctly.
- [x] Failures propagate to Pest.
- [x] Steps execute in deterministic order.

Example:

```php
scenario('addition works', function () {

    given('two numbers', function () {
        $this->a = 2;
        $this->b = 3;
    });

    when('they are added', function () {
        $this->result = $this->a + $this->b;
    });

    then('the result is five', function () {
        expect($this->result)->toBe(5);
    });

});
```

### Success criteria

Met. A scenario runs as an ordinary Pest test. Pest remains the test runner.

---

# Milestone 1: Core DSL

### Status

Complete

### Goal

Implement the complete structural hierarchy.

### Deliverables

Support:

- [x] Feature
- [x] Rule
- [x] Scenario
- [x] Given
- [x] When
- [x] Then

API:

```php
feature(...)
rule(...)
scenario(...)
given(...)
when(...)
then(...)
```

Implement internal nodes:

- [x] `FeatureNode`
- [x] `RuleNode`
- [x] `ScenarioNode`
- [x] `StepNode`

Track source information:

- [x] Source file
- [x] Source line

### Implemented behavior

- `feature()` and `rule()` register Pest `describe()` groups. `scenario()` registers a Pest `it()` test.
- Given, When, and Then steps execute in declaration order within the same Pest test context.
- Each step node records its type, description, source file, and source line.
- A failing step propagates its exception to Pest and prevents later steps from running.
- The runner clears recorded steps before each scenario execution.
- Feature and rule lifecycle hooks run before scenario steps.
- The DSL rejects rules outside features, nested features and rules, scenarios without a rule inside a feature, and steps outside a scenario.

### Success criteria

Met. Tests construct the model hierarchy and run nested feature, rule, and scenario definitions through Pest.

---

# Milestone 2: Behaviour Registry

### Goal

Separate behaviour definition from execution metadata.

### Deliverables

Introduce:

```php
FlowRegistry
```

The registry should expose all discovered:

```text
Features
Rules
Scenarios
Steps
```

Each node receives a stable runtime identifier.

Example:

```text
contractor-activation
contractor-activation/compliance-rule
contractor-activation/compliance-rule/activate-contractor
```

Identifiers should initially be derived rather than manually specified.

### Success criteria

Pest Flow can inspect its entire behaviour structure programmatically.

---

# Milestone 3: Execution Metadata

### Goal

Capture scenario and step execution results.

### Deliverables

Record:

```text
pending
running
passed
failed
skipped
```

Also capture:

```text
duration
exception
source location
```

A scenario failure must still be reported natively by Pest.

### Success criteria

After a run, the behaviour tree accurately represents execution state.

---

# Milestone 4: Console Reporter

### Goal

Make the behavioural structure visible during development.

### Deliverables

Optional Flow output:

```text
Feature: Contractor Activation

  Rule: Only compliant contractors may activate

    ✓ Activate compliant contractor
      ✓ Given a compliant contractor
      ✓ When approval occurs
      ✓ Then contractor becomes active
```

Possible invocation:

```bash
pest --flow
```

### Success criteria

Developers can understand business behaviour directly from terminal output.

---

# Milestone 5: JSON Export

### Goal

Create a stable machine-readable representation.

### Deliverables

Command:

```bash
pest --flow-json
```

Support output file:

```bash
pest --flow-json=flow.json
```

Include:

```text
features
rules
scenarios
steps
tags
status
duration
source
```

### Success criteria

External tools can consume Pest Flow behaviour without parsing PHP.

---

# Milestone 6: Tags and Filtering

### Goal

Allow large behaviour suites to be queried.

### Deliverables

Support tags on:

```text
Feature
Rule
Scenario
```

Example:

```php
scenario('charge customer', ...)
    ->tags('payments', 'critical');
```

Filtering should reuse Pest groups where practical.

Examples:

```bash
pest --group=payments
```

or, only if necessary:

```bash
pest --flow-tag=payments
```

### Success criteria

Developers can run and export meaningful subsets of behaviour.

---

# Milestone 7: Living Documentation

### Goal

Generate human-readable behavioural documentation.

### Deliverables

Static HTML report.

Example:

```bash
pest --flow-report
```

Navigation:

```text
Features
  → Rules
      → Scenarios
          → Steps
```

Display:

```text
status
duration
tags
source file
```

### Success criteria

A non-developer can inspect documented system behaviour without opening test files.

---

# Milestone 8: Flow Viewer

### Goal

Create a richer visual exploration layer.

This should likely exist as a separate package or application.

Possible package:

```text
pest-flow/viewer
```

### Deliverables

Visual browser for:

- feature hierarchy
- rules
- scenarios
- scenario steps
- execution status
- tags
- source links

Potential visual mode:

```text
Given
  ↓
When
  ↓
Then
```

and:

```text
Feature
  ↓
Rule
  ├── Scenario A
  ├── Scenario B
  └── Scenario C
```

### Success criteria

Behaviour can be explored visually rather than only as test output.

---

# Milestone 9: Behaviour Discovery for Agents

### Goal

Make application behaviour easy for coding agents to inspect.

### Deliverables

Commands such as:

```bash
pest --flow-list
```

```bash
pest --flow-export
```

Possible filtering:

```bash
pest --flow-list contractor
```

Structured output should make questions such as these easy to answer:

```text
Which behaviours concern contractor activation?

What rules apply to invoice cancellation?

Which scenarios touch a specific feature?
```

### Success criteria

An agent can understand expected application behaviour without reading the entire test suite.

---

# 19. MVP Boundary

The MVP target should stop after Milestone 5. The current implementation covers Milestones 0 and 1.
Work on Milestones 2 through 5 remains planned.

MVP therefore includes:

- Pest plugin and the Feature, Rule, Scenario, Given, When, and Then DSL (Milestone 1: implemented).
- Scenario execution through Pest (Milestone 1: implemented).
- Structured behaviour registry (Milestone 2: planned).
- Step execution state (Milestone 3: planned).
- Console representation (Milestone 4: planned).
- JSON export (Milestone 5: planned).

It should not initially include:

```text
✗ UI
✗ hosted dashboard
✗ historical test storage
✗ Gherkin
✗ Laravel-specific integrations
✗ browser automation
✗ AI features
```

This keeps the first version technically small while establishing the important part: the behaviour model.

---

# 20. Example MVP Experience

Install:

```bash
composer require pestphp/pest --dev
composer require pest-flow/pest-flow --dev
```

Create:

```php
<?php

use function Pest\Flow\{
    feature,
    rule,
    scenario,
    given,
    when,
    then
};

feature('Contractor activation', function () {

    rule('Only compliant contractors may be activated', function () {

        scenario('Activate compliant contractor', function () {

            given('a compliant contractor', function () {
                $this->contractor =
                    Contractor::factory()
                        ->compliant()
                        ->create();
            });

            when('activation is requested', function () {
                $this->contractor->activate();
            });

            then('the contractor becomes active', function () {
                expect($this->contractor->isActive())
                    ->toBeTrue();
            });

        });

    });

});
```

Run:

```bash
pest --flow
```

Output:

```text
Contractor Activation

  Only compliant contractors may be activated

    ✓ Activate compliant contractor

      ✓ Given a compliant contractor
      ✓ When activation is requested
      ✓ Then the contractor becomes active

1 scenario passed
3 steps passed
```

Export:

```bash
pest --flow-json=flow.json
```

---

# 21. Open Design Questions

Milestone 1 resolved the DSL structure, step execution, and failure behaviour. The remaining
questions apply to later milestones.

### DSL construction (resolved in Milestone 1)

Should the primary API be nested closures:

```php
feature('...', function () {
    rule('...', function () {
        scenario('...', function () {
        });
    });
});
```

or fluent:

```php
feature('...')
    ->rule('...')
    ->scenario('...');
```

The DSL uses nested closures. Features and rules register Pest `describe()` groups, and scenarios
register Pest `it()` tests.

---

### Step execution (resolved for the current implementation)

Steps execute immediately as Pest runs the scenario closure. The runner records each step before it
calls the step closure. A failure stops registration and execution of later steps.

The complete step list is not collected before a scenario starts. Milestone 2's registry will support
programmatic inspection of discovered behaviour.

---

### Failure behaviour (implemented in Milestone 1)

If a `When` step fails:

```text
Given ✓
When  ✗
Then  ?
```

Current behaviour:

```text
Exception reaches Pest; later steps do not run.
```

Milestone 3 will add explicit execution states, including `failed` and `skipped`.

---

### Reusable steps

Classic BDD frameworks often promote reusable step definitions.

Pest Flow should initially avoid this.

Ordinary PHP functions already provide composition:

```php
function compliantContractor(): Contractor
{
    // ...
}
```

Introducing string-to-function step matching would recreate one of the complexities Pest Flow is intended to avoid.

---

### Background

Gherkin has `Background`.

Pest Flow probably does not need a dedicated equivalent initially because Pest already provides:

```php
beforeEach(...)
```

Pest Flow should favour existing Pest primitives when they solve the same problem.

---

### Examples and parameterised scenarios

Pest datasets could provide Scenario Outline-like behaviour.

Example:

```php
scenario('reject invalid contractor', function ($reason) {
    //
})->with([
    'missing tax number',
    'expired contract',
    'missing identity document',
]);
```

This should integrate with Pest datasets rather than introduce a new examples system.

---

# 22. Technical Risks

## Pest internals

The plugin must avoid depending unnecessarily on undocumented Pest internals.

Prefer official extension points.

---

## DSL complexity

It is easy for Pest Flow to turn into a second testing framework.

New functionality should first ask:

```text
Can Pest already do this?
```

If yes, Pest Flow should integrate rather than duplicate.

---

## Gherkin imitation

Copying every Gherkin feature would create unnecessary complexity.

Pest Flow should preserve BDD concepts while taking advantage of PHP as the specification language.

---

## Reporting coupling

The behaviour model should remain independent from console, JSON and UI representations.

Reporters should consume the model rather than define it.

---

# 23. Success Metrics

Early success should be measured through developer behaviour rather than adoption numbers.

Indicators include:

- existing Pest users can understand the API immediately
- existing Pest features continue working
- behavioural hierarchy can be exported without parsing test source
- the plugin adds minimal execution overhead
- developers can find system behaviour faster than by browsing normal test names
- the JSON model is sufficient to build an external viewer without modifying the core plugin

---

# 24. Long-Term Direction

Pest Flow can evolve into three distinct layers:

```text
pest-flow/core
    ↓
Behaviour model

pest-flow/pest
    ↓
Pest execution integration

pest-flow/viewer
    ↓
Visual exploration
```

Initially these can remain in one repository.

They should only become separate packages if genuine reuse emerges.

The long-term value is not the Given/When/Then syntax itself.

The core asset is a machine-readable model of:

```text
What the system does
Why it does it
Which rules govern it
Which scenarios prove it
Whether those behaviours currently work
```

Pest provides the execution engine.

Pest Flow provides the behavioural layer.
