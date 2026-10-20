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

## What a channel cannot do

A `stack` channel can hold only Monolog channels: Laravel builds one Monolog logger from their Monolog handlers. A `hallmark` channel cannot join one. Write to it by name, or make it the default channel.
