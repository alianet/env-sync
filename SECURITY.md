# Security Policy

`env-sync` operates on dotenv files that may contain secrets. Protecting those
values and preserving the integrity of target files are core security
properties of the project.

## Supported versions

Security fixes are provided for the latest released version. Users should
upgrade to the newest release before reporting an issue or requesting a fix;
older versions are not supported.

## Reporting a vulnerability

Do not open a public GitHub issue, discussion, or pull request for a suspected
vulnerability. Report it privately by email to
[m.kaczanowski@alianet.pl](mailto:m.kaczanowski@alianet.pl) with the subject
`[env-sync security] Vulnerability report`.

Include, where possible:

- the affected `env-sync` version and PHP version;
- the operating system and relevant filesystem details;
- a description of the impact and affected security property;
- minimal steps or a proof of concept using synthetic dotenv values only;
- any known mitigations or suggested fixes.

Never include real credentials, tokens, dotenv contents, or other secrets in a
report. Redact sensitive paths and metadata when they are not necessary to
reproduce the issue.

You should receive an acknowledgement within three business days and an
initial assessment within seven business days. Timing for a fix and public
disclosure depends on severity and complexity. Please allow time for a release
to be prepared before publishing details. Credit will be given in the advisory
and release notes unless you prefer to remain anonymous.

## Scope

Security reports are especially welcome for issues that could:

- expose dotenv values through output, errors, logs, or generated files;
- overwrite, remove, or corrupt existing target assignments;
- bypass symbolic-link or atomic-write protections;
- write to an unintended path;
- execute or evaluate dotenv content as code.

The documented display of variable names is not itself a vulnerability, but a
case where names expose more information than documented or expected may still
be reported privately.
