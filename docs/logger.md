# The logger

## Building a logger

```php
new Logger(
    string $name,                  // the channel stamped on every record
    array $handlers = [],          // consulted first to last
    array $processors = [],        // applied first to last
    ?DateTimeZone $timezone = null, // the records' timezone; PHP's default when null
    array $boundContext = [],      // merged into every record's context
);
```

<!-- not run: a signature, not a program -->

`name()` returns the channel. `getHandlers()` and `getProcessors()` return the stacks in the order they are consulted.

## Writing records

The eight PSR-3 methods, `debug()` to `emergency()`, take a message and a context array. `log()` takes the level too, as a `Level` case, a PSR-3 level string or an integer severity:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%level_name%: %message% %context%\n"));
$log = new Logger('app', [$handler]);

$log->notice('Signed in', ['user' => 42]);
$log->log(Level::Error, 'by case');
$log->log('critical', 'by PSR-3 string');
$log->log(550, 'by severity');
```

```text
NOTICE: Signed in {"user":42}
ERROR: by case []
CRITICAL: by PSR-3 string []
ALERT: by severity []
```

An unknown level, as a string or as an integer, throws `Hallmark\Exception\InvalidArgumentException`, which extends `Psr\Log\InvalidArgumentException`, as PSR-3 asks:

```php
use Hallmark\Logger;

try {
    (new Logger('app'))->log(999, 'x');
} catch (Psr\Log\InvalidArgumentException $e) {
    echo $e->getMessage(), "\n";
}
```

```text
Unknown log level severity: 999
```

## Levels

`Level` is a backed enum of the eight RFC 5424 severities. Its values are Monolog's, so tooling that reads Monolog's numbers reads Hallmark's:

| Case | Value | `toPsr()` | `toRfc5424()` |
|---|---|---|---|
| `Level::Debug` | 100 | `debug` | 7 |
| `Level::Info` | 200 | `info` | 6 |
| `Level::Notice` | 250 | `notice` | 5 |
| `Level::Warning` | 300 | `warning` | 4 |
| `Level::Error` | 400 | `error` | 3 |
| `Level::Critical` | 500 | `critical` | 2 |
| `Level::Alert` | 550 | `alert` | 1 |
| `Level::Emergency` | 600 | `emergency` | 0 |

`Level::fromPsr()` reads a PSR-3 string, `Level::fromName()` an upper-case name such as `WARNING`, and `getName()` writes it. `includes()` answers whether a threshold accepts a level; `isHigherThan()` and `isLowerThan()` compare two:

```php
use Hallmark\Level;

echo var_export(Level::Warning->includes(Level::Error), true), "\n";
echo var_export(Level::fromPsr('notice') === Level::Notice, true), "\n";
echo Level::fromName('alert')->getName(), "\n";
```

```text
true
true
ALERT
```

## Records

A `LogRecord` holds `datetime`, `channel`, `level`, `message`, `context` and `extra`. All six are `readonly`, so a handler cannot change what the next one sees. `with()` returns a copy with some fields replaced; processors enrich a record that way. `formatted` is the one writable field: a handler stores its formatter's output there just before writing. `toArray()` gives the record as a plain array.

## The stacks

Both stacks are last in, first out: `pushHandler()` and `pushProcessor()` put a new entry in front of the others, and `popHandler()` and `popProcessor()` take the front one off (on an empty stack they throw `LogicException`).

For each record, the logger offers it to every handler in turn. A handler that does not take the record's level is skipped. A handler that handles it and does not *bubble* stops it there, so the handlers after it never see it:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\NullHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;

$file = new StreamHandler('php://stdout');
$file->setFormatter(new LineFormatter("%level_name%: %message%\n"));

$log = new Logger('app', [$file]);
$log->pushHandler(new NullHandler(Level::Error)); // in front, swallows Error and above

$log->warning('reaches the stream');
$log->error('swallowed by the NullHandler');
```

```text
WARNING: reaches the stream
```

The processors run once per record, and only once some handler has taken it: a record no handler wants costs no processing. `addRecord()` is the method under all of the above; it returns whether any handler took the record.

`isHandling(Level)` answers whether any handler would take a record of that level. The logger asks it first, so a disabled level returns before a record is even built.

## Lazy messages

A message can be a closure. It is called only when a handler will take the record, so building an expensive message costs nothing at a disabled level:

```php
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;

$log = new Logger('app', [new StreamHandler('php://stdout', Level::Warning)]);

$log->debug(function (): string {
    echo "never called\n";

    return 'state dump';
});
echo "done\n";
```

```text
done
```

## Child loggers

`child()` returns a logger that shares this one's handler and processor instances and adds context to every record, for a request or a job. Its stacks are copies: pushing a handler on the child leaves the parent's alone. A context key passed on a call wins over the same bound key. A second argument renames the channel:

```php
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Logger;

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%channel%: %message% %context%\n"));
$log = new Logger('app', [$handler]);

$request = $log->child(['request_id' => 'r-7']);
$request->info('handling');
$request->info('overridden', ['request_id' => 'r-8']);
$log->child([], 'worker')->info('renamed');
```

```text
app: handling {"request_id":"r-7"}
app: overridden {"request_id":"r-8"}
worker: renamed []
```

A child keeps its parent's exception handler (see [long-running workers](long-running-workers.md)).
