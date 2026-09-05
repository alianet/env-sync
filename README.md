# env-sync

`alianet/env-sync` is a framework-independent CLI that compares a local `.env` file with a versioned template and safely appends missing entries. It compares variable names, never their values, so configuration drift can be detected without printing secrets.

## Installation

The package requires PHP 8.2 or newer. Install it as a regular application dependency when it must remain available during production deployment:

```bash
composer require alianet/env-sync
```

This keeps `vendor/bin/env-sync` available after `composer install --no-dev`, including for deployment-time Composer Scripts. If synchronization is needed only during local development or CI, install it as a development dependency instead:

```bash
composer require --dev alianet/env-sync
```

Development dependencies are deliberately absent from production installations performed with `--no-dev`. When developing this package itself, run `composer install` and invoke `bin/env-sync`; consuming applications use the Composer-generated `vendor/bin/env-sync` executable.

## Usage

```bash
vendor/bin/env-sync diff
vendor/bin/env-sync diff .env.example .env
vendor/bin/env-sync diff --format=json .env.example .env
vendor/bin/env-sync update
vendor/bin/env-sync update .env.example .env
vendor/bin/env-sync update --dry-run
vendor/bin/env-sync validate-config
vendor/bin/env-sync validate-config --config=config/env-sync.json
```

Paths default to `.env.example` and `.env`. If `.env.example` is absent and `.env.dist` exists, `.env.dist` is used. `diff` lists missing, additional, and duplicate keys. `update` creates the target when needed and adds missing assignments to the matching blank-line-delimited section in template order. Assignments whose section cannot be matched are appended under `# Added by env-sync`; existing values and additional keys remain untouched.

### Project configuration

Project defaults and accepted local-only keys can be stored in an optional `.env-sync.json` file:

```json
{
    "$schema": "./vendor/alianet/env-sync/env-sync.schema.json",
    "template": ".env.dist",
    "target": ".env.local",
    "allowed_extra_keys": ["APP_DEBUG", "LOCAL_PROXY_URL"],
    "allowed_extra_patterns": ["DEV_*", "CACHE_?"]
}
```

Relative paths in the configuration are resolved from the directory containing that file. Explicit command-line paths override configured paths. Use `--config=path/to/env-sync.json` to select another configuration file. Additional target keys listed in `allowed_extra_keys` or matching `allowed_extra_patterns` are ignored by `diff`; duplicate keys are still reported. Patterns are case-sensitive, match the complete key, and support `*` for any number of characters and `?` for one character. The configuration contains paths and variable names only, never dotenv values.

`validate-config` checks the configuration structure and rules without reading either dotenv file. Unlike `diff` and `update`, it requires `.env-sync.json` or the file selected with `--config` to exist. This makes it suitable for a fast CI check and editor-independent validation.

The package includes `env-sync.schema.json` using JSON Schema draft 2020-12. The optional `$schema` property enables validation and completion in compatible editors. Its relative path is resolved by the editor from the configuration file, so configurations outside the project root should adjust the example path accordingly.

`--dry-run` reports the number of entries that would be added and performs no write. Add `-v` to `update` or `update --dry-run` when key names are useful for diagnosis; values are never displayed.

Use `diff --format=json` for machine-readable output in CI and other tools. The JSON contains paths, key names, difference status, and duplicate keys, but never dotenv values. The exit code remains `0` when key sets match and `1` when differences are found.

```json
{
    "template": ".env.example",
    "target": ".env",
    "has_differences": true,
    "missing": ["CACHE_URL"],
    "additional": ["LOCAL_ONLY"],
    "duplicate_keys": {
        "template": [],
        "target": []
    }
}
```

### Exit codes

| Code | Meaning |
| ---: | --- |
| `0` | Files have the same key set, or update completed/no change was needed |
| `1` | `diff` detected missing, additional, or duplicate keys |
| `2` | Invalid usage, unreadable input, unsafe syntax, or write failure |

## Safety

- Terminal output contains variable names, never values.
- Existing target assignments are never overwritten.
- Additional target keys are never removed.
- Updates are written to a temporary file in the target directory and installed with an atomic rename.
- Symbolic-link targets are rejected rather than replaced with regular files.
- Parse and duplicate-key errors stop an update before writing.
- The consuming project should ignore `.env` and any other secret-bearing target files in its own `.gitignore`; templates should contain non-secret defaults only.

Always review versioned templates and dry-run output. Variable names themselves can occasionally disclose sensitive system design.

## Composer Scripts after install and update

Integration is optional and must be configured in the root `composer.json` of the consuming application. Package dependency scripts are not inherited or executed by Composer. A recommended explicit configuration is:

```json
{
    "scripts": {
        "env:update": "@php vendor/bin/env-sync update .env.example .env",
        "post-install-cmd": [
            "@env:update"
        ],
        "post-update-cmd": [
            "@env:update"
        ]
    }
}
```

For a `.env.dist` template, change the alias to:

```json
{
    "scripts": {
        "env:update": "@php vendor/bin/env-sync update .env.dist .env",
        "post-install-cmd": [
            "@env:update"
        ],
        "post-update-cmd": [
            "@env:update"
        ]
    }
}
```

`update` is non-interactive and idempotent: it creates a missing target, appends missing entries, preserves all existing values, and leaves additional keys in place. A repeated run with unchanged files returns `0` without rewriting the target. Relative paths are resolved from the application directory in which Composer runs, not from the installed package directory.

An invalid or missing template, a parse error, or a write failure returns a non-zero status and can intentionally stop installation. This is useful when a valid deployment file is required, but it should be enabled consciously. Applications can omit both lifecycle hooks and invoke `vendor/bin/env-sync update` manually instead.

Where infrastructure can inject real environment variables directly, that is generally preferable in production. This library only synchronizes dotenv files; it does not load variables into the application process. A `.env` containing secrets must not be committed.

## CI example

```yaml
- name: Validate env-sync configuration
  run: vendor/bin/env-sync validate-config

- name: Check dotenv template compatibility
  run: vendor/bin/env-sync diff --format=json .env.example .env.ci
```

Omit the validation step when the project does not use `.env-sync.json`. Prepare `.env.ci` from CI-safe configuration and never echo its contents. Exit code `1` makes configuration drift fail the job, while JSON output can be retained as a machine-readable CI artifact.

## Current parser limits

The parser preserves comments, blank lines, assignments, `export` assignments, inline comments, and LF/CRLF endings. Blank lines delimit sections, which are matched using shared keys or an identical leading comment heading. It identifies keys without evaluating values or interpolation. It intentionally does not implement full Bash syntax: multiline values, heredocs, shell commands, standalone `export`, and other shell constructs are rejected with a line-numbered error.

## Development

```bash
composer validate --strict
composer test
composer test-production-install
composer phpstan
composer cs-check
composer qa
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for project conventions.

Release notes are maintained in [CHANGELOG.md](CHANGELOG.md).

## Docker

Docker is an optional development environment; local PHP and Composer are not required. It uses only the official PHP CLI image because this project is a library and console tool—there is no web server, PHP-FPM, database, or Redis.

Start with the highest supported PHP version (8.5):

```bash
LOCAL_UID="$(id -u)" LOCAL_GID="$(id -g)" docker compose build php
docker compose run --rm php composer install
docker compose run --rm php composer qa
```

The UID and GID build arguments make files created through the bind mount belong to the local user. They default to `1000` when omitted. After changing either value, rebuild the image; if an existing dependency volume has incompatible ownership, stop the project and recreate only that version's `env-sync-vendor-*` volume.

Individual checks remain available:

```bash
docker compose run --rm php composer test
docker compose run --rm php composer phpstan
docker compose run --rm php composer cs-check
```

Set `PHP_VERSION` consistently for both build and run to work with another version. Rebuild whenever the selected PHP version or Dockerfile changes:

```bash
PHP_VERSION=8.2 LOCAL_UID="$(id -u)" LOCAL_GID="$(id -g)" docker compose build php
PHP_VERSION=8.2 docker compose run --rm php composer install
PHP_VERSION=8.2 docker compose run --rm php composer qa
```

The source tree is bind-mounted at `/app`, so edits are visible immediately. Dependencies are stored in the named volume `env-sync-vendor-<version>` rather than the host tree. This avoids host ownership issues and prevents incompatible `vendor` directories from being shared between PHP versions, but IDEs running on the host will not see those dependencies. Composer's download cache is shared in `env-sync-composer-cache`; sharing downloads is safe because installed dependencies remain separate.

Use the matrix scripts for a fresh, isolated dependency installation and the same complete quality suite used by CI, including the production `--no-dev` installation check:

```bash
./tools/test-php-version 8.2
./tools/test-php-versions
```

| Version | Command |
| --- | --- |
| PHP 8.2 | `./tools/test-php-version 8.2` |
| PHP 8.3 | `./tools/test-php-version 8.3` |
| PHP 8.4 | `./tools/test-php-version 8.4` |
| PHP 8.5 | `./tools/test-php-version 8.5` |

To remove only this project's daily-use containers and networks, run `docker compose down`. If dependencies need a clean reinstall, remove only the selected version's volume (for example `docker volume rm env-sync-vendor-8.2`) after its containers have stopped. The matrix scripts remove only their own temporary images and do not prune Docker's global cache, images, or volumes.

The native PHP matrix remains the primary CI implementation and covers the same PHP versions and quality checks. Compose works with Docker Engine on Linux and Docker Desktop on macOS or Windows; on Windows, run the Bash matrix scripts from WSL or Git Bash.
