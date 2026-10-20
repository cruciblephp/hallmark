# Changelog

Every release, as it ships.

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
