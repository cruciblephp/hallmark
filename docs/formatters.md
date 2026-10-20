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
    bool $includeStacktraces = true,
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
{"message":"empty bags","level":200,"level_name":"INFO","channel":"app","datetime":"2026-10-19","context":{},"extra":{}}
{"message":"a context","level":200,"level_name":"INFO","channel":"app","datetime":"2026-10-19","context":{"order":4711},"extra":{}}
```

`formatBatch()`, which the decorators call, lays out several records as one JSON array (`BatchMode::Json`) or one object per line (`BatchMode::Newlines`).

## Values

Both formatters normalize the context and the extra before writing them:

| Value | Written as |
|---|---|
| A string, integer, boolean or `null` | Itself |
| `NAN`, `INF`, `-INF` | `"NaN"`, `"INF"`, `"-INF"` |
| A `DateTimeInterface` | A string in the date format |
| A `Throwable` | Its class, message, code, `file:line`, its trace when stacktraces are on, and its previous exception |
| A `JsonSerializable` | What `jsonSerialize()` returns, normalized in turn |
| A `Stringable` | Its string |
| Any other object | `{"Class": {its public properties}}` |
| A resource | `[resource(stream)]` |

Normalization stops 9 levels deep and after 1000 items in one array, so a cyclic or enormous value can never stall the logger. `includeStacktraces()` turns the exception trace on or off after construction; the line formatter leaves it off by default and the JSON formatter on:

```php
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new JsonFormatter(dateFormat: 'Y-m-d', includeStacktraces: false));
$log = new Logger('app', [$handler]);

$log->error('import failed', [
    'ratio'     => NAN,
    'exception' => new RuntimeException('disk full', 28),
]);
```

```text
{"message":"import failed","level":400,"level_name":"ERROR","channel":"app","datetime":"2026-10-19","context":{"ratio":"NaN","exception":{"class":"RuntimeException","message":"disk full","code":28,"file":"sample.php:13"}},"extra":{}}
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
