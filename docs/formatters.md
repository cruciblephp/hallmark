# Formatters

A formatter turns a record into the string a handler writes. `setFormatter()` gives a handler its own; without one, it uses a `LineFormatter`.

## `LineFormatter`

```php
new LineFormatter(
    ?string $format = null,              // LineFormatter::DEFAULT_FORMAT when null
    ?string $dateFormat = null,
    bool $allowInlineLineBreaks = false,
    bool $ignoreEmptyContextAndExtra = false,
    bool $includeStacktraces = false,
);
```

<!-- not run: a signature, not a program -->

The format is a template of placeholders:

| Placeholder | Becomes |
|---|---|
| `%datetime%` | The record's time |
| `%channel%` | The logger's name |
| `%level%` | The level's value, `300` |
| `%level_name%` | The level's name, `WARNING` |
| `%message%` | The message |
| `%context%` | The context, as JSON |
| `%extra%` | The extra, as JSON |
| `%context.key%`, `%extra.key%` | One value, which then leaves `%context%` or `%extra%`; nothing when the key is absent |

The default is `[%datetime%] %channel%.%level_name%: %message% %context% %extra%` and a newline. A newline inside a message or a value becomes a space, so one record is always one line; `$allowInlineLineBreaks` keeps them. `$ignoreEmptyContextAndExtra` writes nothing for an empty bag instead of `[]`:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%level_name% %message% %context%%extra%\n", ignoreEmptyContextAndExtra: true));
$log = new Logger('app', [$handler]);

$log->info("multi\nline");
$log->info('with context', ['id' => 1]);
```

```text
INFO multi line 
INFO with context {"id":1}
```

## `JsonFormatter`

```php
new JsonFormatter(
    BatchMode $batchMode = BatchMode::Json,
    bool $appendNewline = true,
    ?string $dateFormat = null,
    bool $ignoreEmptyContextAndExtra = false,
    bool $includeStacktraces = false,
    ?int $maxLineBytes = null,
);
```

<!-- not run: a signature, not a program -->

One JSON object per record, the feed for a structured log store. An empty `context` or `extra` is written as `{}`, as it is when populated, so a store that types a field by the first value it sees reads an object every time:

```php
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new JsonFormatter(dateFormat: 'Y-m-d'));
$log = new Logger('app', [$handler]);

$log->info('empty bags');
$log->info('a context', ['order' => 4711]);
```

```text
{"message":"empty bags","context":{},"level":200,"level_name":"INFO","channel":"app","datetime":"2026-10-19","extra":{}}
{"message":"a context","context":{"order":4711},"level":200,"level_name":"INFO","channel":"app","datetime":"2026-10-19","extra":{}}
```

`formatBatch()`, which the decorators call, lays out several records as one JSON array (`BatchMode::Json`) or one object per line (`BatchMode::Newlines`).

### A line cap

`$maxLineBytes` caps each record's JSON, its newline counted, for a writer that keeps a line whole only up to a size, such as a shell script appending to the same file. A record that would pass the cap has its longest string in the message, the context or the extra cut until it fits, so the short facts survive whole, and the record says so with `extra.truncated`. A cut never splits a UTF-8 character. When no string is left to cut, the context and the extra go. A record whose channel and date alone pass the cap is refused with a `RuntimeException`, so a line is never written over it:

```php
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new JsonFormatter(dateFormat: 'Y-m-d', maxLineBytes: 220));
$log = new Logger('app', [$handler]);

$log->info('import done', ['file' => 'orders.csv', 'rows' => str_repeat('row,', 100)]);
$log->info('short', ['file' => 'orders.csv']);
```

```text
{"message":"import done","context":{"file":"orders.csv","rows":"row,row,row,row,row,row,row,row,row,row,row,row,row,r"},"level":200,"level_name":"INFO","channel":"app","datetime":"2026-10-29","extra":{"truncated":true}}
{"message":"short","context":{"file":"orders.csv"},"level":200,"level_name":"INFO","channel":"app","datetime":"2026-10-29","extra":{}}
```

Monolog has no line cap: with `$maxLineBytes` left `null`, the JSON is Monolog's.

## Values

Both formatters normalize the context and the extra before writing them:

| Value | `JsonFormatter` | `LineFormatter` |
|---|---|---|
| A string, integer, boolean or `null` | Itself | Itself |
| `NAN`, `INF`, `-INF` | `0`, as JSON cannot hold them | `"NaN"`, `"INF"`, `"-INF"` |
| A string that is not valid UTF-8 | The bad bytes as `�` | The same |
| A `DateTimeInterface` | A string in the date format | The same |
| A `Throwable` | `{"class", "message", "code", "file"}`, its `trace` when stacktraces are on, and its `previous` | `[object] (Class(code: 3): message at file:line)`, a `[stacktrace]` when on, and each `[previous exception]` |
| A `JsonSerializable` | What `jsonSerialize()` returns, as `json_encode()` writes it | `{"Class": what jsonSerialize() returns}` |
| A `Stringable` | Its string, or its class when `__toString()` throws | `{"Class": "its string"}`, or its public properties when `__toString()` throws |
| Any other object | Its public properties, as `json_encode()` writes them | `{"Class": {its public properties}}` |
| A resource | `"[resource(stream)]"` | The same |

An anonymous class is named `class@anonymous`, or `Parent@anonymous`, without the file it was declared in. Each is what Monolog writes.

Normalization stops 9 levels below the record and after 1000 items in one array, so a cyclic or enormous value can never stall the logger. Both formatters leave the exception trace off by default, as Monolog's do:

```php
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new JsonFormatter(dateFormat: 'Y-m-d'));
$log = new Logger('app', [$handler]);

$log->error('import failed', [
    'ratio'     => NAN,
    'exception' => new RuntimeException('disk full', 28),
]);
```

```text
{"message":"import failed","context":{"ratio":0,"exception":{"class":"RuntimeException","message":"disk full","code":28,"file":"sample.php:13"}},"level":400,"level_name":"ERROR","channel":"app","datetime":"2026-10-19","extra":{}}
```

## Stack traces

`$includeStacktraces`, or `includeStacktraces()` after construction, adds the trace. In the JSON, each frame is written as `file:line`, as Monolog writes it, and never with the arguments of the call, so a password passed to the function that threw does not reach the log, whatever `zend.exception_ignore_args` says. A frame the engine called, with no file of its own, is written `internal[function]:0`. `setBasePath()` strips a folder from every file name, and `setMaxTraceLength()` keeps the first frames only:

```php
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

function charge(string $card): void
{
    throw new RuntimeException('declined');
}

$handler = new StreamHandler('php://stdout');
$handler->setFormatter((new JsonFormatter(dateFormat: 'Y-m-d', includeStacktraces: true))->setBasePath(__DIR__)->setMaxTraceLength(10));
$log = new Logger('app', [$handler]);

try {
    charge('4242 4242 4242 4242');
} catch (RuntimeException $e) {
    $log->error('charge failed', ['exception' => $e]);
}
```

```text
{"message":"charge failed","context":{"exception":{"class":"RuntimeException","message":"declined","code":0,"file":"sample.php:9","trace":["sample.php:17"]}},"level":400,"level_name":"ERROR","channel":"app","datetime":"2026-10-20","extra":{}}
```

On a line, the trace is a `[stacktrace]` block in the layout of PHP's `getTraceAsString()`, and turning it on keeps line breaks, as in Monolog. Each call is written without its arguments, `charge()`: what Monolog writes under PHP's production ini. Under the development ini, Monolog's line carries the arguments, their first 15 characters, `charge('4242 4242 4242 ...')`; Hallmark's never does.

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

function charge(string $card): void
{
    throw new RuntimeException('declined');
}

$handler = new StreamHandler('php://stdout');
$handler->setFormatter((new LineFormatter("%message% %context%\n", includeStacktraces: true))->setBasePath(__DIR__));
$log = new Logger('app', [$handler]);

try {
    charge('4242 4242 4242 4242');
} catch (RuntimeException $e) {
    $log->error('charge failed', ['exception' => $e]);
}
```

```text
charge failed {"exception":"[object] (RuntimeException(code: 0): declined at sample.php:9)
[stacktrace]
#0 sample.php(17): charge()
#1 {main}
"}
```

## Dates

With no date format, both formatters write the record's own time with microseconds and a date found in the context to the second, as Monolog does:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Level;
use Hallmark\LogRecord;

$record = new LogRecord(
    new DateTimeImmutable('2026-10-20T12:00:00.123456+00:00'),
    'app',
    Level::Info,
    'dated',
    ['due' => new DateTimeImmutable('2026-10-21T09:00:00.5+00:00')],
);

echo (new LineFormatter())->format($record);
echo (new LineFormatter(dateFormat: 'Y-m-d'))->format($record);
```

```text
[2026-10-20T12:00:00.123456+00:00] app.INFO: dated {"due":"2026-10-21T09:00:00+00:00"} []
[2026-10-20] app.INFO: dated {"due":"2026-10-21"} []
```

A `dateFormat` applies to both. `NormalizerFormatter::RECORD_DATE` and `NormalizerFormatter::SIMPLE_DATE` hold the two defaults.

## Writing a formatter

Implement `FormatterInterface`: `format(LogRecord): string` and `formatBatch(array): string`. Extending `NormalizerFormatter` gives `normalize()`, the date handling above and a `formatBatch()` that joins `format()`.
