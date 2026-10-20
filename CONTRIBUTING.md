# Contributing

Thank you for considering a contribution. This page says how a change is judged and how to check one before sending it.

## How a change is judged

In this order; an earlier rule wins over a later one.

1. **PSR-3 and Monolog's shape first.** Hallmark implements `Psr\Log\LoggerInterface` and keeps the handler, formatter and processor architecture Monolog made the standard. Where Monolog already writes something a certain way (the severity values, a JSON record, a placeholder date), Hallmark writes it the same way, so the tooling that reads Monolog's output keeps reading Hallmark's. A difference from Monolog is measured against Monolog itself, not assumed.
2. **Standards at the boundary.** PSR-3 decides how a caller reaches the logger, and Laravel's channel keys decide how a Laravel application configures it. Neither decides what a record carries: the context and extra are the caller's own.
3. **Tests prove every claim.** A rule in the manual, the README, a docblock or the CHANGELOG has a test that holds it. A fix comes with the test that fails without it: break the fix, watch the test fail, restore it. A claim nothing can test is not made.
4. **One change per commit.** A commit is named for what it delivers, with `composer check` green. A reviewer reads one thing at a time, and `git bisect` lands on one thing.

## The one command

```bash
composer check
```

runs Composer's strict PSR-4 check, php-cs-fixer (`--dry-run`), Rector (`--dry-run`), PHPStan at level max over the source, the integrations and the tests, and the test suite on Crucible, and fails on the first that fails. CI runs it on PHP 8.3, 8.4 and 8.5, then fails if the run changed any tracked file (`git diff --exit-code`).

Do not read its result through a pipe: `composer check | tail` exits with `tail`'s status, which is 0, whatever the check did. Run it on its own, or with `set -o pipefail`, and look at the exit code.

## Before you send a change

- The manual and the README say what the change does, with every sample run as written.
- The CHANGELOG's unreleased entry has a line for a user-visible change: one line, starting with `Added`, `Fixed`, `Changed`, `Improved`, `Removed`, `Deprecated`, `Updated` or `Security`, with no trailing period.
- Coverage needs a coverage driver: `pecl install pcov`, or xdebug loaded on demand. `vendor/bin/crucible --coverage` prints it.
- A change to the site (`index.html`, `docs.html`, `changelog.html`, `assets/`, `docs/`) passes `node scripts/check-site.mjs`, which loads every page in a browser.

## Security

Do not open a public issue for a vulnerability. See [SECURITY.md](SECURITY.md).
