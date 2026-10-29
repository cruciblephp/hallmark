# Changelog

Every release, as it ships.

## v1.4.0 - 2026-10-29

- Added `IdGenerator`, taken by `UidProcessor`, so the application gives the ids its own kind, such as the ULIDs Laravents mints; in Laravel, binding the interface is enough for a channel's processors

## v1.3.1 - 2026-10-29

- Removed `NormalizerFormatter::RFC3339_MS`: `dateFormat` already takes `Y-m-d\TH:i:s.vp`, which writes `Z` for a UTC time and the offset otherwise

## v1.3.0 - 2026-10-29

- Added `NormalizerFormatter::RFC3339_MS`, RFC 3339 to the millisecond: a UTC time ends in `Z`, any other in its offset
- Added `event()` to the Laravel channel's logger, so `Log::channel('hallmark')->event()` logs a catalogued event

## v1.2.0 - 2026-10-29

- Added `maxLineBytes` to `JsonFormatter`, a cap on each record's line: a record over it has its longest strings cut on a UTF-8 boundary until it fits and is marked `extra.truncated`, and one whose fixed fields alone pass it is refused
- Added `perRecord` to `UidProcessor`, so each record gets a uid of its own, an id for the line itself
- Added the `formatter`, `formatter_with` and `processors` keys to the Laravel `hallmark` channel, read as Laravel's log manager reads them for every channel

## v1.1.0 - 2026-10-20

- Security: an exception's trace never carries the call's arguments, so a password passed to the function that threw no longer reaches the log past `RedactionProcessor` when `zend.exception_ignore_args` is off; the JSON writes each frame as `file:line` and a line writes what Monolog writes under PHP's production ini
- Changed `JsonFormatter` to leave stack traces off by default, as Monolog's does; pass `includeStacktraces: true` to keep them
- Changed the JSON's key order to Monolog's: `message`, `context`, `level`, `level_name`, `channel`, `datetime`, `extra`
- Added `setBasePath()` and `setMaxTraceLength()` to the formatters, as in Monolog, to strip a folder from file names and cap the frames kept
- Changed `JsonFormatter` to write values as Monolog's does: a `NAN` or `INF` as `0`, a `JsonSerializable`, a plain object and an enum as `json_encode()` writes them, a `Stringable` whose `__toString()` throws as its class name
- Changed `LineFormatter` to write values as Monolog's does: an exception as `[object] (Class(code: N): message at file:line)` with a `[stacktrace]` block and each `[previous exception]`, a `Stringable`, a `JsonSerializable`, an enum and a plain object under their class name, an object's properties as `json_encode()` writes them, and an escaped line break inside JSON as a real one when line breaks are kept
- Changed `LineFormatter` so turning stack traces on keeps line breaks, as in Monolog
- Added `%context.key%` and `%extra.key%` placeholders to `LineFormatter`, as in Monolog
- Fixed a `Stringable` whose `__toString()` throws failing the whole record; it is written as Monolog writes it
- Fixed an anonymous class written with a NUL byte and the file it was declared in; it is written `class@anonymous` or `Parent@anonymous`
- Fixed invalid UTF-8 in a value turning it into `null`; the bad bytes are written as U+FFFD, as in Monolog
- Fixed the depth and item limits to count from the record, as Monolog does, and the item limit's note to give the total
- Fixed a Laravel `hallmark` channel dropping a `days` read from `env()`, a string, and writing one file that never rotated; numbers and booleans are read as `env()` gives them, and a value the channel cannot use is refused with an `InvalidArgumentException` naming the key
- Added `StackHandler`, so a `hallmark` channel can join a Laravel `stack` channel
- Added `*` wildcards to `RedactionProcessor` keys, such as `*_token`
- Changed `StreamHandler` to throw a `RuntimeException` when a write fails or falls short, instead of losing the record without a word
- Fixed two docs samples failing where the temp folder is a symlink, as on macOS
- Added a test that runs every kind of value through Hallmark's and Monolog's formatters, under each option, and compares the output; the two differences the README names, a line trace's arguments and a placeholder inside a value, have tests of their own
- Added CI jobs on macOS and with PHP's development ini
- Updated `cruciblephp/crucible` to `^1.1.7`, which scopes a `beforeEach` inside `describe()` to its block

## v1.0.0 - 2026-10-20

- Added `Logger`, a PSR-3 logger with LIFO handler and processor stacks, lazy processor evaluation, lazy messages, child loggers with bound context, `reset()` for long-running workers and an optional exception handler
- Added `Level`, the eight RFC 5424 severities as a backed enum, with PSR-3, name and syslog conversions and ordering helpers
- Added `LogRecord`, an immutable value object with a `with()` copy-constructor
- Added the `StreamHandler`, `RotatingFileHandler`, `StderrHandler`, `SyslogHandler`, `NullHandler` and `TestHandler` handlers, the `BufferHandler`, `FingersCrossedHandler` and `SamplingHandler` decorators, and the `AbstractHandler` and `AbstractProcessingHandler` bases
- Added the `LineFormatter` and `JsonFormatter` formatters, with `BatchMode` and the shared `NormalizerFormatter` base
- Added the `PsrLogMessageProcessor`, `IntrospectionProcessor`, `MemoryUsageProcessor`, `UidProcessor`, `CorrelationIdProcessor` and `RedactionProcessor` processors
- Added the event catalog: `EventCode` enums logged with `Logger::event()` and checked with `EventCatalog`
- Added the `AssertLog` and `LogRecordConstraint` assertions on PHPUnit's API, which also run under Crucible
- Added the Laravel log driver `hallmark`, auto-discovered, configured with Laravel's own channel keys and carrying Laravel's Context into every record
- Added the test suite on Crucible, PHPStan at level max over the source, the integrations and the tests, and the Rector and php-cs-fixer configuration
- Added CI on PHP 8.3, 8.4 and 8.5, with coverage uploaded to code scanning as SARIF
