# Security Policy

## Supported versions

| Version | Status |
|---|---|
| 2.0.x | Maintained — security fixes and bug patches |
| 1.x | Security fixes only until 2027-05 |
| < 1.0 | Not supported (pre-Packagist as `casamento/rbac`) |

## Reporting a vulnerability

Please **do not** open a public GitHub issue for security reports.

Instead, report privately by emailing **christophertheilacher@gmail.com** with:

- A description of the issue and where it lives in the code.
- Steps to reproduce (proof-of-concept welcome — keep it minimal).
- The version(s) affected.
- Any suggested mitigation, if you have one.

You will get an acknowledgement within 72 hours. Once the report is triaged we'll coordinate disclosure: a fixed release goes out, then a public advisory + CVE if appropriate.

## What counts as a security issue

- Authorization bypass: a path that grants permissions to an actor who shouldn't hold them (via the HTTP API, the Gate, the `HasAccessPermissions` trait, or the Spatie sync gateway).
- Tenant boundary leakage: data from one tenant exposed to another via use-case or query.
- Injection vectors via input passed through FormRequests or value objects.
- Audit log forgery or tampering via the package's own writers.

## What doesn't

- Misconfiguration in a host application using the package (configure your guards, middleware, and policies correctly).
- Bugs that require an attacker to already have full admin privileges in the host.
- Issues in `spatie/laravel-permission` itself — report those upstream at https://github.com/spatie/laravel-permission/security.
- Issues in the host's Laravel framework, web server, or DB — out of scope here.
