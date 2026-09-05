# Changelog

All notable changes to this project will be documented here. The format follows Keep a Changelog, and the project intends to follow Semantic Versioning.

## Release v1.0.1

### Added

- Release validation for version consistency between Git tags, the `VERSION` file, CLI output, and this changelog.
- CI coverage reporting, lowest-supported dependency checks, dependency update configuration, and community health files.

### Changed

- The CLI version and help output now use the packaged `VERSION` file as their single source of truth.
- Docker development images now run as the configured host user to avoid bind-mount ownership issues.
- User-facing CLI failures now use dedicated exception types, while unexpected programming errors are allowed to surface.

### Fixed

- Parsing of escaped quote characters in single-quoted dotenv values.

## Release v1.0.0

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
