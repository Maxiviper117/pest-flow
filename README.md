# Pest Flow

Pest Flow is a Pest 5 plugin for writing executable behaviour specifications in PHP. Its planned `Feature → Rule → Scenario → Given/When/Then` API and MVP are described in [the PRD](docs/PRD.md).

This repository currently contains the package and development scaffold. The runtime DSL and reporting features in the PRD have not been implemented yet.

## Requirements

- PHP 8.4 or newer
- Pest 5

## Development

```sh
composer install
composer test:lint
composer test:types
composer test:refacto
```

Run the full suite, including Pest tests, with `composer test` after tests have been added.

## Releases

Google's Release Please action opens or updates a release pull request on pushes to `main`. Merging that PR creates the version tag and GitHub release. Use [Conventional Commits](https://www.conventionalcommits.org/) so Release Please can determine the version bump and changelog.

The package stays below `1.0.0` until the project deliberately moves to v1. Before v1, `feat:` changes bump the minor version, `fix:` changes bump the patch version, and breaking changes bump the minor version.
