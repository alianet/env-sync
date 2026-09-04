# Contributing

Use PHP 8.2-compatible syntax and keep domain logic independent of the command-line adapter. Every PHP source file must enable strict types. Preserve dotenv lines unless a requested operation explicitly changes them, and never include real secrets in source, fixtures, assertions, logs, or failure messages.

Install dependencies with `composer install`, then run before submitting a change:

```bash
composer validate --strict
composer test
composer test-production-install
composer phpstan
composer cs-check
```

Add focused unit tests for parser and domain behavior and integration tests for observable CLI or filesystem behavior. Integration tests must use temporary directories and must not touch the repository's `.env`.
