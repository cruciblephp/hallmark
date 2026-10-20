# Security Policy

## Supported versions

Security fixes are released for the latest minor version only.

| Version | Supported |
|---|---|
| 1.0.x (latest minor) | Yes |
| Anything older | No: upgrade to the latest minor |

## Reporting a vulnerability

Report a vulnerability privately, by email to **info@cruciblephp.com**. Do not open a public issue, pull request or discussion for it.

Please include the Hallmark and PHP versions (and Laravel's, for the `hallmark` driver), what an attacker can do, and the smallest reproduction you have (a failing test is ideal).

## What to expect

- An acknowledgement within 3 working days.
- An assessment (accepted or not, and why) within 10 working days.
- For an accepted report, a fix released as a patch version, and a GitHub security advisory crediting the reporter unless they ask not to be named. The timeline depends on the severity and is agreed with the reporter; the details stay private until the fix is released.

## In scope

The parts of Hallmark that decide what reaches a log and what it touches on disk:

- **`RedactionProcessor`**: a key in its list whose value reaches a formatter unmasked, at any depth of the context.
- **`LineFormatter` and `JsonFormatter`**: a value that forges a second record (a newline the line formatter should collapse, a JSON document that breaks out of its object), or normalization that runs code it should only describe.
- **`StreamHandler` and `RotatingFileHandler`**: a file written outside the configured path, created with other permissions than `filePermission`, or deleted by retention when it is not one of the handler's own dated files.
- **The `hallmark` Laravel driver**: a value from Laravel's Context, or from a channel's configuration, that reaches a record or a file it should not.

## Out of scope

- What an application chooses to log. Hallmark writes the context it is given; keeping secrets out of it, or listing their keys for `RedactionProcessor`, is the caller's part.
- The permissions and retention of the directories an application points the handlers at.
- Vulnerabilities in PHP, Laravel or a PSR-3 consumer: report those to their maintainers.
