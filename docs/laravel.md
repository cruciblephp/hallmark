# Laravel

## The `hallmark` driver

Package discovery registers `HallmarkServiceProvider`, which adds a `hallmark` driver to Laravel's log manager. A channel takes the keys of Laravel's own `single` driver, and `days` from `daily`:

```php
// config/logging.php
'channels' => [
    'hallmark' => [
        'driver' => 'hallmark',
        'path'   => storage_path('logs/laravel.log'),
        'level'  => env('LOG_LEVEL', 'debug'),
    ],
],
```

<!-- not run: Laravel configuration; tests/Laravel/HallmarkChannelTest.php holds it -->

| Key | Default | Does |
|---|---|---|
| `path` | `storage/logs/laravel.log` | The file the channel writes |
| `level` | `debug` | The lowest level written, a PSR-3 level name |
| `bubble` | `true` | Whether a record goes on to the next handler |
| `permission` | none | The file's permissions, set when it is opened |
| `locking` | `false` | `flock()` around each write |
| `replace_placeholders` | `false` | Interpolate `{placeholder}` tokens, as `PsrLogMessageProcessor` does |
| `name` | the environment | The channel name on each record |
| `days` | none | One file per day, `days` of them kept, as `RotatingFileHandler` writes them |
| `formatter` | the handler's own, a `LineFormatter` | A formatter class, built by the container; `default` keeps the handler's own |
| `formatter_with` | none | The formatter's constructor arguments, by name |
| `processors` | none | Processor classes, each a class or `['processor' => class, 'with' => arguments]`, run after the channel's own |

The last three are read as Laravel's log manager reads them for every channel. A channel that writes one JSON object per line, each line at most 4,096 bytes and carrying an id of its own:

```php
// config/logging.php
'hallmark' => [
    'driver'         => 'hallmark',
    'path'           => storage_path('logs/laravel.log'),
    'formatter'      => \Hallmark\Formatter\JsonFormatter::class,
    'formatter_with' => ['maxLineBytes' => 4096],
    'processors'     => [
        ['processor' => \Hallmark\Processor\UidProcessor::class, 'with' => ['length' => 16, 'perRecord' => true]],
    ],
],
```

<!-- not run: Laravel configuration; tests/Laravel/HallmarkChannelTest.php holds it -->

A processor is built by the container, so an interface it takes is filled from the application's bindings: binding `Hallmark\Processor\IdGenerator` in a service provider gives `UidProcessor` the application's kind of id, with nothing in the config that `config:cache` cannot hold.

A value is read as `env()` gives it: `env('LOG_DAILY_DAYS', 14)` is the string `"14"` when the variable is set, and is read as `14`; a boolean may be `"1"` or `"0"`. Any other value, `'days' => 'fourteen'`, is refused with an `InvalidArgumentException` naming the key, rather than dropped: a channel that silently stops rotating is worse than one that fails. Laravel catches it and logs the failure, and the channel's records after it, through its emergency logger, `storage/logs/laravel.log` unless an `emergency` channel names another path.

Then the channel is used as any other:

```php
Log::channel('hallmark')->warning('Low disk space', ['free_mb' => 128]);
```

<!-- not run: needs a Laravel application; tests/Laravel/HallmarkChannelTest.php holds it -->

`'driver' => 'custom', 'via' => CreateHallmarkLogger::class` builds the same channel from the same keys, as Laravel's custom channels are built.

## Laravel's Context

On a Monolog channel, Laravel adds its Context to every record. A `hallmark` channel does the same, through Hallmark's own `ContextLogProcessor`, so whatever is in Context appears in each record's `extra`: a request id, or the `correlation_id` and `causation_id` that Laravents puts there for the message being handled.

```php
Context::add('correlation_id', 'req-42');
Log::channel('hallmark')->error('payment failed');
// [...] testing.ERROR: payment failed [] {"correlation_id":"req-42"}
```

<!-- not run: needs a Laravel application; tests/Laravel/HallmarkChannelTest.php holds it -->

## The logger behind the channel

`Log::channel('hallmark')` returns Laravel's `Illuminate\Log\Logger`, as every channel does. Behind it is a `ChannelLogger`, and behind that the Hallmark `Logger`, to push a handler or a processor:

```php
$hallmark = Log::channel('hallmark')->getLogger()->getLogger();
$hallmark->pushProcessor(new IntrospectionProcessor(Level::Warning));
```

<!-- not run: needs a Laravel application; tests/Laravel/HallmarkChannelTest.php holds it -->

## Why a driver

Laravel's log manager calls `isHandling()`, `pushProcessor()` and `getHandlers()` on a channel that has methods by those names, and it calls them with Monolog's types: a level string, Laravel's own Monolog processor, Monolog handlers. Hallmark's `Logger` has methods by those names with its own types, so a bare `Logger` returned from `Log::extend()` fails on its first line. `ChannelLogger` is a plain PSR-3 logger that hides them.

## In a `stack`

A `stack` channel builds one Monolog logger from the Monolog handlers of its channels. A `hallmark` channel gives it one, `StackHandler`, which forwards each record to the Hallmark logger, so it joins a stack as any other channel does:

```php
// config/logging.php
'stack' => [
    'driver'   => 'stack',
    'channels' => ['hallmark', 'slack'],
],
```

<!-- not run: Laravel configuration; tests/Laravel/HallmarkChannelTest.php holds it -->

The record keeps its level, message, context and time. The `extra` added by the stack's processors stays behind; the `hallmark` channel's own processors, Laravel's Context among them, add theirs.
