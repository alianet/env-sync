# Changelog

All notable changes to this project will be documented here. The format follows Keep a Changelog, and the project intends to follow Semantic Versioning.

## [Unreleased]

### Added

- Initial `diff` and `update` commands.
- Structure-preserving dotenv document parser.
- Atomic target writes and `--dry-run` support.
- PHPUnit, PHPStan, PHP-CS-Fixer, and CI configuration.
- Production dependency and Composer lifecycle-script guidance.
- Quiet, idempotent update summaries with optional verbose key names.
- A local path-repository test covering `composer install --no-dev` and Composer hooks.
- Section-aware placement of missing assignments in template order.
- JSON output for `diff` using `--format=json`.
- Optional `.env-sync.json` project configuration for template and target paths, including command-line path overrides and paths resolved relative to the configuration file.
- Rules for accepted target-only variables through exact `allowed_extra_keys` entries and case-sensitive `allowed_extra_patterns` globs.
- A `validate-config` command for checking configuration without reading dotenv files.
- A bundled JSON Schema draft 2020-12 document for `.env-sync.json` validation and editor completion.
