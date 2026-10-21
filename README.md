# Hallmark

**A PSR-3 logger for PHP 8.3+, with Monolog's handlers, formatters and processors, and Monolog's output.**

`Hallmark\Logger` implements `Psr\Log\LoggerInterface`. Levels are an enum, records are `readonly`, and its only dependency is `psr/log`. The level values, the JSON and the dates match Monolog's, so tools that read Monolog's logs read these too. Laravel gets a `hallmark` log driver.

The manual is in [docs/](docs/README.md), and the site is [cruciblephp.github.io/hallmark](https://cruciblephp.github.io/hallmark/). Every PHP sample in this README and in the manual runs in the test suite.

## Installation

```bash
composer require hallmark/hallmark
```

## The thirty-second example

A logger has a channel name, handlers that write records, and processors that add to them:

```php
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Processor\CorrelationIdProcessor;
use Hallmark\Processor\PsrLogMessageProcessor;
use Hallmark\Processor\RedactionProcessor;

$stdout = new StreamHandler('php://stdout', Level::Info);
$stdout->setFormatter(new JsonFormatter());

$log = new Logger('shop', [$stdout], [new CorrelationIdProcessor(id: 'req-7f3a')]);
$log->pushProcessor(new PsrLogMessageProcessor());
$log->pushProcessor(new RedactionProcessor());

$log->debug('cart loaded');
$log->warning('Payment {order} retried', ['order' => 4711, 'card_number' => '4242424242424242']);
```

```text
{"message":"Payment 4711 retried","context":{"order":4711,"card_number":"[REDACTED]"},"level":300,"level_name":"WARNING","channel":"shop","datetime":"2026-10-19T22:37:03.265717+00:00","extra":{"correlation_id":"req-7f3a"}}
```

`debug()` is below the handler's level, so no record is built. The card number is masked before the message is interpolated, and every line carries the correlation id.

## Examples

Both examples use the Beatles fan-club site from Laravents' docs. The test suite runs them and checks their output.

- [`examples/fan-club`](examples/fan-club/README.md) uses Hallmark alone: an event catalog for the band's events, a signup with its email and password masked, and a worker that runs the press's jobs on one logger.
- [`examples/laravents`](examples/laravents/README.md) runs the same site on Laravents with a `hallmark` channel. The command's log line and its event's log line share the request's correlation id, and the event's line names the command as its cause.

## Handlers, formatters and processors

| Handlers | Formatters | Processors |
|---|---|---|
| `StreamHandler`, `RotatingFileHandler`, `StderrHandler`, `SyslogHandler`, `NullHandler`, `TestHandler`, and the `BufferHandler`, `FingersCrossedHandler` and `SamplingHandler` decorators | `LineFormatter`, `JsonFormatter` | `PsrLogMessageProcessor`, `IntrospectionProcessor`, `MemoryUsageProcessor`, `UidProcessor`, `CorrelationIdProcessor`, `RedactionProcessor` |

Each is documented on the [handlers](docs/handlers.md), [formatters](docs/formatters.md) and [processors](docs/processors.md) pages. `FingersCrossedHandler` holds a request's records and writes them only if one reaches its activation level:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\FingersCrossedHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;

$stream = new StreamHandler('php://stdout');
$stream->setFormatter(new LineFormatter("%level_name%: %message%\n"));
$log = new Logger('app', [new FingersCrossedHandler($stream, activationLevel: Level::Error)]);

$log->debug('cart loaded');
$log->info('charging card');
$log->error('card declined');
```

```text
DEBUG: cart loaded
INFO: charging card
ERROR: card declined
```

## The event catalog

An `EventCode` enum gives an event a fixed code, with its level and message derived from the case:

```php
use Hallmark\Event\EventCode;
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;

enum PaymentEvent: string implements EventCode
{
    case AuthorizationFailed = 'PAY-1001';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return Level::Error;
    }

    public function message(): string
    {
        return 'Payment authorization failed';
    }
}

$stdout = new StreamHandler('php://stdout');
$stdout->setFormatter(new LineFormatter("%level_name%: %message% %context%\n"));

(new Logger('shop', [$stdout]))->event(PaymentEvent::AuthorizationFailed, ['order' => 4711]);
```

```text
ERROR: Payment authorization failed {"order":4711,"event_code":"PAY-1001"}
```

`EventCatalog::assertValid()` checks a catalog in a test: the code format, duplicate codes and empty messages. See the [event catalog page](docs/event-catalog.md).

## Laravel

Package discovery registers a `hallmark` log driver. A channel takes the same keys as Laravel's `single` driver, plus `days` from `daily`:

```php
// config/logging.php
'channels' => [
    'hallmark' => [
        'driver' => 'hallmark',
        'path'   => storage_path('logs/laravel.log'),
        'level'  => env('LOG_LEVEL', 'debug'),
        'days'   => 14,
    ],
],
```

<!-- not run: Laravel configuration; tests/Laravel/HallmarkChannelTest.php holds it -->

Laravel's Context is added to every record's `extra`, as on a Monolog channel. The [Laravel page](docs/laravel.md) lists the keys and explains why this is a driver rather than a `Log::extend()` callback.

## Testing

`TestHandler` keeps the records in memory, and the `AssertLog` trait asserts on them; a failed assertion lists what was logged. It uses PHPUnit's API and also runs on [Crucible](https://github.com/cruciblephp/crucible):

```php
use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\PHPUnit\AssertLog;
use PHPUnit\Framework\TestCase;

final class CheckoutTest extends TestCase
{
    use AssertLog;

    public function testItWarnsOnRetry(): void
    {
        $handler = new TestHandler();
        (new Logger('shop', [$handler]))->warning('payment retry scheduled');

        $this->assertLoggedThatContains($handler, Level::Warning, 'retry');
    }
}
```

<!-- not run: a test case; tests/PHPUnit/AssertLogTest.php holds it -->

All assertions are on the [testing page](docs/testing.md).

## Development

```bash
composer install
composer check
```

`composer check` runs Composer's strict PSR-4 check, php-cs-fixer, Rector, PHPStan at level max and the test suite on Crucible. CI runs it on PHP 8.3, 8.4 and 8.5. Coverage needs pcov or xdebug:

```bash
vendor/bin/crucible --coverage
```

See [CONTRIBUTING.md](CONTRIBUTING.md) before sending a change, and [SECURITY.md](SECURITY.md) to report a vulnerability.

## Relationship to Monolog

Hallmark is an independent implementation of Monolog's design and shares no code with it. Its level values, JSON, lines and dates are compared against Monolog's own output in the test suite and match it, with two differences, both where Monolog writes what was not logged: a stack trace on a line never carries the call's arguments, which Monolog's does under PHP's development ini, and a `%placeholder%` inside a logged value is written as it is, where Monolog's line formatter expands it.

## Credits

Hallmark is written and maintained by Luciano Federico Pereira.

## License

MIT. See [LICENSE](LICENSE).
