# Processors

A processor takes a record and returns one, usually a copy with something added to `extra`. Any `callable(LogRecord): LogRecord` is a processor; the ones below implement `ProcessorInterface`. On a logger they run once per record, only when some handler takes it; on a handler (`pushProcessor()` there) they run for that handler only.

| Processor | Does |
|---|---|
| `PsrLogMessageProcessor` | Replaces `{placeholder}` tokens in the message with context values |
| `IntrospectionProcessor` | Adds the call site: `file`, `line`, `class`, `function` |
| `MemoryUsageProcessor` | Adds `memory_usage` |
| `UidProcessor` | Adds the same random `uid` to every record |
| `CorrelationIdProcessor` | Adds a correlation id, adoptable from upstream and resettable |
| `RedactionProcessor` | Masks sensitive context values by key |

## `PsrLogMessageProcessor`

`PsrLogMessageProcessor(?string $dateFormat = null, bool $removeUsedContextFields = false)` interpolates PSR-3 placeholders: a name of letters, digits, `_` and `.`, in braces, with a context key of that name. A date is written with `$dateFormat`, or `Y-m-d\TH:i:s.uP` as Monolog writes it; an array as JSON; `null` as nothing; an object without `__toString()` as `[object Class]`. With `$removeUsedContextFields`, an interpolated key leaves the context, so it is not written twice:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\Processor\PsrLogMessageProcessor;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%message% %context%\n"));
$log = new Logger('app', [$handler], [new PsrLogMessageProcessor(removeUsedContextFields: true)]);

$log->info('User {id} signed in at {at}', [
    'id' => 42,
    'at' => new DateTimeImmutable('2026-10-20T08:30:00+00:00'),
    'ip' => '203.0.113.9',
]);
```

```text
User 42 signed in at 2026-10-20T08:30:00.000000+00:00 {"ip":"203.0.113.9"}
```

## `IntrospectionProcessor`

`IntrospectionProcessor(Level $level = Level::Debug, array $skipClassesPartials = [], int $skipStackFramesCount = 0)` adds where the logging call was made. It skips Hallmark's own logger, handler and processor frames, and the class names you add; `$skipStackFramesCount` skips more frames, for a wrapper of your own. Walking the stack is not free, so it runs only for records at `$level` or above:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\Processor\IntrospectionProcessor;

final class Checkout
{
    public function __construct(private Logger $log) {}

    public function pay(): void
    {
        $this->log->warning('retrying the gateway');
    }
}

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%message% %extra%\n"));
(new Checkout(new Logger('app', [$handler], [new IntrospectionProcessor()])))->pay();
```

```text
retrying the gateway {"file":"sample.php","line":14,"class":"Checkout","function":"pay"}
```

## `MemoryUsageProcessor`

`MemoryUsageProcessor(bool $realUsage = true, bool $useFormatting = true)` adds `memory_usage`, from `memory_get_usage($realUsage)`: a string such as `2 MB`, or the bytes as an integer without formatting.

## `UidProcessor` and `CorrelationIdProcessor`

Both stamp every record with one random hex id, so the lines of a request can be found together, and both get a new one on `reset()`.

`UidProcessor(int $length = 7)` writes `extra.uid`, 1 to 32 characters. `getUid()` returns it.

`CorrelationIdProcessor(string $key = 'correlation_id', ?string $id = null, int $length = 16)` writes `extra.correlation_id`, or the key you give, 1 to 64 characters. `adopt()` takes an id from upstream, such as an incoming `X-Request-Id`, and `reset($id)` starts the next one. A record that already carries the key, in its context or its extra, is left as it is, so a line never has two ids:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\Processor\CorrelationIdProcessor;

$correlation = new CorrelationIdProcessor();
$correlation->adopt('req-9f2c');

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%message% %context% %extra%\n"));
$log = new Logger('app', [$handler], [$correlation]);

$log->info('adopted');
$log->info('passed in the call', ['correlation_id' => 'job-77']);
```

```text
adopted [] {"correlation_id":"req-9f2c"}
passed in the call {"correlation_id":"job-77"} []
```

## `RedactionProcessor`

`RedactionProcessor(array $keys = RedactionProcessor::DEFAULT_KEYS, string $replacement = '[REDACTED]', bool $recursive = true)` masks the value of every context key in its list, whatever the case, at any depth. The default list covers auth and payment data: `password`, `passwd`, `secret`, `token`, `api_key`, `apikey`, `authorization`, `auth`, `access_token`, `refresh_token`, `client_secret`, `private_key`, `card`, `card_number`, `cvv`, `cvc`, `pan`, `ssn`.

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\Processor\RedactionProcessor;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%message% %context%\n"));
$log = new Logger('app', [$handler], [new RedactionProcessor()]);

$log->info('login', ['user' => 'sam', 'Password' => 'hunter2', 'payment' => ['card_number' => '4242424242424242', 'amount' => 100]]);
```

```text
login {"user":"sam","Password":"[REDACTED]","payment":{"card_number":"[REDACTED]","amount":100}}
```

It reads the context only: a secret already written into the message is out of its reach.

## Order

The processors run from the most recently pushed to the first. Redaction must run before interpolation, or a secret is spliced into the message before it is masked: push the interpolation first and the redaction last.

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\Processor\PsrLogMessageProcessor;
use Hallmark\Processor\RedactionProcessor;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%message%\n"));
$log = new Logger('app', [$handler]);
$log->pushProcessor(new PsrLogMessageProcessor());
$log->pushProcessor(new RedactionProcessor());

$log->info('token {token}', ['token' => 'abc123']);
```

```text
token [REDACTED]
```

## A closure as a processor

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\LogRecord;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%message% %extra%\n"));
$log = new Logger('app', [$handler]);
$log->pushProcessor(static fn (LogRecord $record): LogRecord => $record->with(
    extra: [...$record->extra, 'region' => 'eu-west'],
));

$log->info('tagged');
```

```text
tagged {"region":"eu-west"}
```
