# Long-running workers

On Octane, RoadRunner or a queue worker one logger serves many requests. A buffer that was never flushed, a fingers-crossed trigger, a sampling count or a correlation id would carry over from one request to the next.

## `reset()`

`Logger::reset()` resets every handler and processor that implements `Resettable`. Call it at each boundary, for instance in Octane's `RequestTerminated` listener:

| Component | `reset()` |
|---|---|
| `BufferHandler` | Flushes what it holds |
| `FingersCrossedHandler` | Drops its buffer and waits for the next trigger |
| `SamplingHandler` | Restarts the count |
| `UidProcessor` | Draws a new uid |
| `CorrelationIdProcessor` | Draws a new id, or takes the one passed |

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\BufferHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\Processor\CorrelationIdProcessor;

$stream = new StreamHandler('php://stdout');
$stream->setFormatter(new LineFormatter("%message% %extra%\n"));
$correlation = new CorrelationIdProcessor(id: 'request-1');
$log = new Logger('app', [new BufferHandler($stream)], [$correlation]);

$log->info('first request');
$log->reset();               // flushes the buffer
$correlation->reset('request-2');
$log->info('second request');
$log->reset();
```

```text
first request {"correlation_id":"request-1"}
second request {"correlation_id":"request-2"}
```

A handler or a processor of your own joins in by implementing `Resettable`.

## `close()`

`Logger::close()` closes every handler: a stream it opened is closed, a buffer is flushed, a fingers-crossed buffer that never triggered is dropped. It is for the end of the process, not a request: `reset()` is the boundary.

## When a handler fails

A handler or a processor that throws, on a full disk or an unwritable path, throws into the code that logged. To keep logging from breaking the request, give the logger an exception handler. It receives the exception and the record; child loggers inherit it, and `setExceptionHandler(null)` restores rethrowing:

```php
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\LogRecord;

$blocker = __DIR__ . '/not-a-directory';
file_put_contents($blocker, '');

$log = new Logger('app', [new StreamHandler($blocker . '/app.log')]);
$log->setExceptionHandler(function (Throwable $e, LogRecord $record): void {
    echo 'Logging failed for "', $record->message, '": ', $e::class, "\n";
});

$log->error('payment failed');
echo "the request goes on\n";
```

```text
Logging failed for "payment failed": Hallmark\Exception\RuntimeException
the request goes on
```
