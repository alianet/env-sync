# Agent Guide

## Architecture

- `src/Document`: lossless line model, parser, and append-only updater.
- `src/Diff`: key-set comparison and its immutable result.
- `src/Application`: use-case orchestration and file loading.
- `src/Filesystem`: atomic persistence boundary.
- `src/Console`: dependency-free command-line parsing and presentation only.
- `tests`: unit and temporary-directory integration tests.

Domain comparison and update code must not depend on console interfaces. Keep line objects capable of retaining their original representation so later releases can place additions into sections without replacing the parser.

## Quality commands

Run `composer validate --strict`, `composer test`, `composer test-production-install`, `composer phpstan`, and `composer cs-check`. Support PHP 8.2 through every currently tested CI version. The production-install test uses an isolated Composer path repository and must never target the repository's own `.env`.

## Secret safety

Never print, log, or embed dotenv values in exceptions. CLI reports may contain paths and variable names only. Do not read or modify a real repository `.env` in tests; use isolated temporary directories. Updates must remain append-only with respect to keys, preserve existing values, validate both documents first, and use atomic persistence. Do not introduce automatic Composer lifecycle hooks that mutate `.env`.
