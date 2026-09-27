# Contributing

## Setup

Install PHP 8.4+ and Composer, then run:

```sh
composer install
```

The package follows the scope and milestones in [docs/PRD.md](docs/PRD.md). New implementation should use Pest's supported extension points and keep Pest as the test runner.

## Checks

Before opening a pull request, run the available checks:

```sh
composer test:lint
composer test:types
composer test:refacto
```

When a Pest suite is present, also run `composer test:unit` or `composer test`.

## Commit messages

Use Conventional Commit messages, for example:

- `feat: add scenario DSL`
- `fix: preserve scenario context`
- `docs: clarify plugin setup`

Release Please uses these commits to prepare release notes and select version bumps.
