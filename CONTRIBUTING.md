# Contributing

Contributions are welcome as focused issues and pull requests. For usage questions and proposed enhancements, search existing issues before opening a new one. Report suspected vulnerabilities privately according to [SECURITY.md](SECURITY.md), never in a public issue.

## Development principles

Use PHP 8.2-compatible syntax and keep domain logic independent of the command-line adapter. Every PHP source file must enable strict types. Preserve dotenv lines unless a requested operation explicitly changes them, and never include real secrets in source, fixtures, assertions, logs, or failure messages.

Keep changes small and scoped to one concern. Describe observable behavior and compatibility implications in the issue or pull request. Breaking changes require explicit discussion before implementation.

## Local checks

Install dependencies with `composer install`, then run before submitting a change:

```bash
composer validate --strict
composer test
composer test-production-install
composer phpstan
composer cs-check
```

Add focused unit tests for parser and domain behavior and integration tests for observable CLI or filesystem behavior. Integration tests must use temporary directories and must not touch the repository's `.env`.

The optional Docker environment is documented in [README.md](README.md). Run `./tools/test-php-versions` when a change may behave differently across supported PHP versions.

## Pull requests

Open pull requests against `main`. Include a concise description, the reason for the change, tests performed, and documentation or changelog updates when user-visible behavior changes. Link related issues using GitHub closing keywords when appropriate.

Maintainers may request changes to preserve backward compatibility, secret safety, lossless parsing, append-only updates, or atomic persistence. Reviews should discuss the code and behavior respectfully and follow [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md).
