# Handlers

A handler decides whether it takes a record, formats it and writes it somewhere. Every handler has a minimum level (`Level::Debug` unless given) and a *bubble* flag (true unless given): a handler that does not bubble stops the record reaching the handlers after it.

| Handler | Writes to |
|---|---|
| `StreamHandler` | A file path or any stream, with optional locking |
| `RotatingFileHandler` | One file per day of each record's own date, with a retention window |
| `StderrHandler` | `php://stderr` |
| `SyslogHandler` | The system logger, through `syslog()` |
| `NullHandler` | Nowhere: it swallows what it takes |
| `TestHandler` | Memory, for assertions (see [testing](testing.md)) |
| `BufferHandler` | Another handler, in one batch |
| `FingersCrossedHandler` | Another handler, only once something goes wrong |
| `SamplingHandler` | Another handler, one record in N |

## `StreamHandler`

```php
new StreamHandler(
    $stream,                     // a path, a URL such as php://stdout, or an open resource
    Level $level = Level::Debug,
    bool $bubble = true,
    ?int $filePermission = null, // applied when the handler opens the file
    bool $useLocking = false,    // flock() around each write
);
```

<!-- not run: a signature, not a program -->

The stream is opened on the first write, in append mode, and a missing directory is created. A resource you pass is yours: `close()` leaves it open. With `$useLocking`, each write holds an exclusive lock, so processes appending to the same file never interleave a line.

```php
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$log = new Logger('app', [new StreamHandler(__DIR__ . '/var/logs/app.log', useLocking: true)]);
$log->error('Payment failed', ['order' => 4711]);

echo file_get_contents(__DIR__ . '/var/logs/app.log');
```

```text
[2026-10-19T22:35:01.856800+00:00] app.ERROR: Payment failed {"order":4711} []
```

`StderrHandler(Level $level = Level::Debug, bool $bubble = true)` is a `StreamHandler` on `php://stderr`, the right place for a CLI tool or a container, where standard output belongs to the program.

## `RotatingFileHandler`

```php
new RotatingFileHandler(
    string $filename,            // app.log becomes app-2026-10-20.log
    int $maxFiles = 0,           // how many dated files to keep; 0 keeps them all
    Level $level = Level::Debug,
    bool $bubble = true,
    ?int $filePermission = null,
    bool $useLocking = false,
    string $dateFormat = RotatingFileHandler::FILE_PER_DAY, // 'Y-m-d'
);
```

<!-- not run: a signature, not a program -->

Each record goes to the file for its own date, not the date it is written on, so a backlog replayed across midnight lands in the right files:

```php
use Hallmark\Handler\RotatingFileHandler;
use Hallmark\Level;
use Hallmark\LogRecord;

$handler = new RotatingFileHandler(__DIR__ . '/logs/app.log', maxFiles: 7);

foreach (['2026-10-17T23:59:59+00:00', '2026-10-18T00:00:01+00:00'] as $time) {
    $handler->handle(new LogRecord(new DateTimeImmutable($time), 'app', Level::Info, 'replayed'));
}
$handler->close();

foreach (glob(__DIR__ . '/logs/*.log') as $file) {
    echo basename($file), "\n";
}
```

```text
app-2026-10-17.log
app-2026-10-18.log
```

With `$maxFiles`, the handler deletes the oldest dated files when it starts a file. It reads the date in each name with `$dateFormat`, so a day-first format such as `d-m-Y` keeps the newest files too, and it never touches a file whose name it could not have written (`app-backup.log` stays).

## `SyslogHandler`

```php
new SyslogHandler(
    string $ident,               // the name syslog records the message under
    int $facility = LOG_USER,
    Level $level = Level::Debug,
    bool $bubble = true,
    int $logopts = LOG_PID,
);
```

<!-- not run: writes to the machine's system log -->

Each level maps onto its syslog priority, from `LOG_DEBUG` to `LOG_EMERG`.

## `NullHandler`

`NullHandler(Level $level = Level::Debug)` takes every record at or above its level and writes nothing. It never bubbles, so pushed in front of a stack it silences those levels for every handler behind it: the [logger page](logger.md#the-stacks) has an example.

## The decorators

`BufferHandler`, `FingersCrossedHandler` and `SamplingHandler` wrap another handler and decide when it sees a record.

### `BufferHandler`

`BufferHandler(HandlerInterface $handler, int $bufferLimit = 0, bool $flushOnOverflow = true)` keeps records in memory and hands them to the wrapped handler as one batch on `flush()`, on `close()`, on `reset()`, and when the object is destroyed. With a `$bufferLimit`, a full buffer is flushed first, or, with `$flushOnOverflow` false, its oldest record is dropped.

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\BufferHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$stream = new StreamHandler('php://stdout');
$stream->setFormatter(new LineFormatter("%message%\n"));
$buffer = new BufferHandler($stream);
$log = new Logger('app', [$buffer]);

$log->info('one');
$log->info('two');
echo "nothing written yet\n";
$buffer->flush();
```

```text
nothing written yet
one
two
```

### `FingersCrossedHandler`

`FingersCrossedHandler(HandlerInterface $handler, Level $activationLevel = Level::Warning, int $bufferSize = 0)` keeps every record, at every level, until one reaches `$activationLevel`. Then it hands the wrapped handler everything it kept, the trigger included, and passes records through from then on. A request that goes well writes nothing; one that fails writes its whole lead-up. `$bufferSize` keeps only the latest records; `close()` drops a buffer that never triggered, and `reset()` empties it and waits for the next trigger.

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
echo "nothing written yet\n";
$log->error('card declined');
$log->info('after the trigger');
```

```text
nothing written yet
DEBUG: cart loaded
INFO: charging card
ERROR: card declined
INFO: after the trigger
```

### `SamplingHandler`

`SamplingHandler(HandlerInterface $handler, int $factor)` passes one record in every `$factor` to the wrapped handler: the 1st in N is dropped, the Nth kept, deterministically, so a chatty path survives a flood. `reset()` restarts the count.

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\SamplingHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$stream = new StreamHandler('php://stdout');
$stream->setFormatter(new LineFormatter("%message%\n"));
$log = new Logger('app', [new SamplingHandler($stream, 3)]);

foreach (range(1, 7) as $n) {
    $log->info("request $n");
}
```

```text
request 3
request 6
```

## A handler's formatter and processors

A handler built on `AbstractProcessingHandler` (all of the above that write) has its own formatter, `LineFormatter` by default, and its own processor stack, which runs after the logger's and only for that handler:

```php
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;
use Hallmark\LogRecord;

$text = new StreamHandler('php://stdout');
$text->setFormatter(new LineFormatter("%message% %extra%\n"));

$json = new StreamHandler('php://stdout');
$json->setFormatter(new JsonFormatter(dateFormat: 'Y-m-d'));
$json->pushProcessor(static fn (LogRecord $r): LogRecord => $r->with(extra: ['sink' => 'json']));

$log = new Logger('app', [$text, $json]);
$log->info('shipped', ['order' => 7]);
```

```text
shipped []
{"message":"shipped","level":200,"level_name":"INFO","channel":"app","datetime":"2026-10-19","context":{"order":7},"extra":{"sink":"json"}}
```

## Writing a handler

Extend `AbstractProcessingHandler` and write one method. By the time `write()` runs, the level is checked, the handler's processors have run and `$record->formatted` holds the formatter's output:

```php
use Hallmark\Handler\AbstractProcessingHandler;
use Hallmark\Logger;
use Hallmark\LogRecord;

final class ArrayHandler extends AbstractProcessingHandler
{
    /** @var list<string> */
    public array $lines = [];

    protected function write(LogRecord $record): void
    {
        $this->lines[] = $record->formatted;
    }
}

$handler = new ArrayHandler();
(new Logger('app', [$handler]))->warning('kept in memory');

echo count($handler->lines), "\n";
```

```text
1
```

A handler that does not format, such as a decorator, implements `HandlerInterface` directly: `getLevel()`, `isHandling()`, `handle()`, `handleBatch()` and `close()`. `getLevel()` must be a floor: the logger skips a level below every handler's `getLevel()` without building the record.
