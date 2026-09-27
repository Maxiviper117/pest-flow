# Pest Flow examples

Each PHP file is a Pest test file. The examples use ordinary Pest expectations and the same test
context for all Given/When/Then callbacks in a scenario.

After installing Composer dependencies, run all examples from the project root by passing their
file paths to Pest:

~~~sh
vendor/bin/pest examples/contractor-activation.php examples/execution-report.php examples/invoice-approval.php examples/pest-hooks.php examples/standalone-scenario.php
~~~

Run one example by passing its file path:

~~~sh
vendor/bin/pest examples/contractor-activation.php
~~~

## Examples

| File | What it demonstrates |
| --- | --- |
| [contractor-activation.php](./contractor-activation.php) | A complete Feature → Rule → Scenario flow, shared state, generated IDs, and registry inspection. |
| [invoice-approval.php](./invoice-approval.php) | Multiple business rules with separate successful outcomes. |
| [pest-hooks.php](./pest-hooks.php) | Feature and rule `beforeEach()` hooks alongside scenario steps. |
| [standalone-scenario.php](./standalone-scenario.php) | Adding a standalone scenario to an existing Pest suite without declaring a Feature or Rule. |
| [execution-report.php](./execution-report.php) | Checking final execution status, duration, exception, step source, and building a small report in `afterEach()`. |

## How to adapt them

- Keep scenario callbacks focused on declaring steps. Put setup and actions inside the step
  callbacks so the order is explicit.
- Use `beforeEach()` on a Feature or Rule for setup shared by its Pest tests; use Given steps for
  state specific to one scenario.
- Use `FlowRegistry` from a step or Pest hook when you need recorded nodes. Step callbacks run
  before the scenario reaches its final status, so inspect final status and duration in `afterEach()`.
- Features and rules are optional for gradual adoption. A standalone `scenario()` is an ordinary
  Pest test with recorded Given/When/Then steps.

The registry is process-local. Examples that inspect it select nodes by name so they also work
when Pest runs the whole `examples/` directory in one process.
