# Pest Flow

Pest Flow is a Pest 5 plugin for writing executable behaviour specifications in PHP. Its DSL organizes tests as `Feature → Rule → Scenario → Given/When/Then`; see the [documentation site](https://maxiviper117.github.io/pest-flow/) for installation, walkthroughs, and API details.

The core DSL, behaviour registry, and execution metadata from Milestones 1 through 3 are implemented. FlowRegistry exposes features, rules, scenarios, and steps with execution status, duration, and captured exceptions. Console reporting, JSON export, and generated living documentation are planned for later milestones.

## Requirements

- PHP 8.4 or newer
- Pest 5

## Usage

Import Pest Flow's namespaced functions in a Pest test file:

```php
use function Pest\Flow\{
    feature,
    rule,
    scenario,
    given,
    when,
    then,
};

feature('Calculator', function (): void {
    rule('Addition returns the sum', function (): void {
        scenario('Add two numbers', function (): void {
            given('two numbers', function (): void {
                $this->left = 2;
                $this->right = 3;
            });

            when('they are added', function (): void {
                $this->result = $this->left + $this->right;
            });

            then('the sum is five', function (): void {
                expect($this->result)->toBe(5);
            });
        });
    });
});
```

Each scenario is registered as a normal Pest test. Feature and rule declarations use Pest `describe` groups, so their lifecycle hooks continue to apply.

## Examples

See [examples/README.md](examples/README.md) for runnable examples covering the full DSL, multiple business rules, Pest lifecycle hooks, standalone scenarios, and execution metadata.

## Development

```sh
composer install
composer test:lint
composer test:types
composer test:refacto
```

Run the full suite with `composer test`.

## Releases

Google's Release Please action opens or updates a release pull request on pushes to `main`. Merging that PR creates the version tag and GitHub release. Use [Conventional Commits](https://www.conventionalcommits.org/) so Release Please can determine the version bump and changelog.

The package stays below `1.0.0` until the project deliberately moves to v1. Before v1, `feat:` changes bump the minor version, `fix:` changes bump the patch version, and breaking changes bump the minor version.

## Documentation

Read the [Pest Flow documentation](https://maxiviper117.github.io/pest-flow/). Run `pnpm install` and `pnpm run dev` to work on the site locally; create a production build with `pnpm run build`.
The documentation site requires Node.js 22.12 or newer.
