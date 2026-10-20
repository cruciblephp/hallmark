# Installation

## Requirements

- PHP **8.3** or newer.
- [`psr/log`](https://www.php-fig.org/psr/psr-3/) `^3.0`, the shared interface. It is the only package Hallmark needs at run time.

The integrations need what they integrate with, and nothing is installed for them:

| To use | You also need |
|---|---|
| The `hallmark` Laravel log driver | Laravel 13 |
| The `AssertLog` assertions | Crucible, or PHPUnit |

## Installing

```bash
composer require hallmark/hallmark
```

Hallmark declares that it provides `psr/log-implementation`, so a package that asks for "a PSR-3 logger" accepts it.

## The first logger

A logger has a channel name and a list of handlers. This one writes warnings and above to standard output, one line each:

```php
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;

$log = new Logger('app', [
    new StreamHandler('php://stdout', Level::Warning),
]);

$log->info('Cache warmed');
$log->warning('Low disk space', ['free_mb' => 128]);
```

```text
[2026-10-19T22:35:00.838915+00:00] app.WARNING: Low disk space {"free_mb":128} []
```

The `info()` line is below the handler's level, so only the warning is written. The time is the record's own, in the logger's timezone, with microseconds.

`Logger` is a `Psr\Log\LoggerInterface`: hand it to anything that type-hints the interface. The [logger page](logger.md) has the rest of its API.

## In Laravel

Package discovery registers the `hallmark` log driver. A channel in `config/logging.php` then needs only `'driver' => 'hallmark'`. The [Laravel page](laravel.md) has the channel keys.

## In a test suite

The `AssertLog` trait asserts on what a `TestHandler` captured. It is written against PHPUnit's API and runs unchanged under Crucible. The [testing page](testing.md) has the assertions.
