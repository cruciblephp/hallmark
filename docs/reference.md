# Reference

Every public class, by namespace, with its constructor and its public methods. The pages before this one explain them; this one lists them.

## `Hallmark`

| Class | Constructor and methods |
|---|---|
| `Logger` | `__construct(string $name, array $handlers = [], array $processors = [], ?DateTimeZone $timezone = null, array $boundContext = [])` · the eight PSR-3 methods and `log(mixed $level, string\|Stringable\|Closure $message, array $context = [])` · `event(EventCode $event, array $context = [])` · `addRecord(Level $level, string\|Stringable\|Closure $message, array $context = [], ?DateTimeImmutable $datetime = null): bool` · `isHandling(Level $level): bool` · `child(array $context = [], ?string $name = null): self` · `name(): string` · `pushHandler()`, `popHandler()`, `getHandlers()` · `pushProcessor()`, `popProcessor()`, `getProcessors()` · `setExceptionHandler(?Closure $callback): self`, `getExceptionHandler(): ?Closure` · `close()` · `reset()` |
| `Level` (enum) | Cases `Debug` 100, `Info` 200, `Notice` 250, `Warning` 300, `Error` 400, `Critical` 500, `Alert` 550, `Emergency` 600 · `static fromPsr(string\|self $level): self` · `static fromName(string $name): self` · `getName(): string` · `toPsr(): string` · `toRfc5424(): int` · `includes(self $level): bool` · `isHigherThan(self $level): bool` · `isLowerThan(self $level): bool` |
| `LogRecord` | `__construct(DateTimeImmutable $datetime, string $channel, Level $level, string $message, array $context = [], array $extra = [], string $formatted = '')` · `with(...)`, any field by name, returns a copy · `toArray(): array` |
| `Resettable` (interface) | `reset(): void` |

## `Hallmark\Handler`

| Class | Constructor and methods |
|---|---|
| `HandlerInterface` | `getLevel(): Level` · `isHandling(LogRecord $record): bool` · `handle(LogRecord $record): bool` · `handleBatch(array $records): void` · `close(): void` |
| `AbstractHandler` | `__construct(Level $level = Level::Debug, bool $bubble = true)` · `setLevel()`, `getLevel()` · `setBubble()`, `getBubble()` |
| `AbstractProcessingHandler` | `AbstractHandler` with a formatter and a processor stack; a subclass writes `write(LogRecord $record): void` |
| `StreamHandler` | `__construct($stream, Level $level = Level::Debug, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false)` · `getUrl(): ?string` |
| `RotatingFileHandler` | `__construct(string $filename, int $maxFiles = 0, Level $level = Level::Debug, bool $bubble = true, ?int $filePermission = null, bool $useLocking = false, string $dateFormat = self::FILE_PER_DAY)` |
| `StderrHandler` | `__construct(Level $level = Level::Debug, bool $bubble = true)` |
| `SyslogHandler` | `__construct(string $ident, int $facility = LOG_USER, Level $level = Level::Debug, bool $bubble = true, int $logopts = LOG_PID)` |
| `NullHandler` | `__construct(Level $level = Level::Debug)` |
| `TestHandler` | `getRecords()` · `getRecordsForLevel(Level)` · `hasRecords(Level)` · `hasRecord(Level, string)` · `hasRecordThatContains(Level, string)` · `hasErrorThatContains(string)` · `hasWarningThatContains(string)` · `clear()` |
| `BufferHandler` | `__construct(HandlerInterface $handler, int $bufferLimit = 0, bool $flushOnOverflow = true)` · `flush()` · `reset()` |
| `FingersCrossedHandler` | `__construct(HandlerInterface $handler, Level $activationLevel = Level::Warning, int $bufferSize = 0)` · `reset()` |
| `SamplingHandler` | `__construct(HandlerInterface $handler, int $factor)` · `reset()` |
| `FormattableHandlerInterface`, `FormattableHandlerTrait` | `setFormatter(FormatterInterface $formatter)` · `getFormatter(): FormatterInterface` |
| `ProcessableHandlerInterface`, `ProcessableHandlerTrait` | `pushProcessor(ProcessorInterface\|callable $processor)` · `popProcessor(): callable` |

## `Hallmark\Formatter`

| Class | Constructor and methods |
|---|---|
| `FormatterInterface` | `format(LogRecord $record): string` · `formatBatch(array $records): string` |
| `NormalizerFormatter` | `__construct(?string $dateFormat = null, int $maxNormalizeDepth = 9, int $maxNormalizeItemCount = 1000)` · `includeStacktraces(bool $include = true): static` · `setBasePath(string $path = ''): static` · `setMaxTraceLength(?int $maxTraceLength): static` · constants `SIMPLE_DATE`, `RECORD_DATE` |
| `LineFormatter` | `__construct(?string $format = null, ?string $dateFormat = null, bool $allowInlineLineBreaks = false, bool $ignoreEmptyContextAndExtra = false, bool $includeStacktraces = false)` · constant `DEFAULT_FORMAT` |
| `JsonFormatter` | `__construct(BatchMode $batchMode = BatchMode::Json, bool $appendNewline = true, ?string $dateFormat = null, bool $ignoreEmptyContextAndExtra = false, bool $includeStacktraces = false)` |
| `BatchMode` (enum) | `Json`, `Newlines` |

## `Hallmark\Processor`

| Class | Constructor and methods |
|---|---|
| `ProcessorInterface` | `__invoke(LogRecord $record): LogRecord` |
| `PsrLogMessageProcessor` | `__construct(?string $dateFormat = null, bool $removeUsedContextFields = false)` · constant `DEFAULT_DATE` |
| `IntrospectionProcessor` | `__construct(Level $level = Level::Debug, array $skipClassesPartials = [], int $skipStackFramesCount = 0)` |
| `MemoryUsageProcessor` | `__construct(bool $realUsage = true, bool $useFormatting = true)` |
| `UidProcessor` | `__construct(int $length = 7)` · `getUid(): string` · `reset()` |
| `CorrelationIdProcessor` | `__construct(string $key = 'correlation_id', ?string $id = null, int $length = 16)` · `getId(): string` · `adopt(string $id)` · `reset(?string $id = null)` |
| `RedactionProcessor` | `__construct(array $keys = self::DEFAULT_KEYS, string $replacement = '[REDACTED]', bool $recursive = true)`; a `*` in a key is a wildcard · constant `DEFAULT_KEYS` |

## `Hallmark\Event`

| Class | Constructor and methods |
|---|---|
| `EventCode` (interface) | `code(): string` · `level(): Level` · `message(): string` |
| `EventCatalog` | `static validate(iterable $enums, string $codePattern = self::DEFAULT_CODE_PATTERN): array` · `static assertValid(iterable $enums, string $codePattern = self::DEFAULT_CODE_PATTERN): void` |

## `Hallmark\Exception`

| Class | What |
|---|---|
| `Exception` (interface) | Every exception Hallmark throws implements it |
| `InvalidArgumentException` | An unknown level, a bad argument; extends `Psr\Log\InvalidArgumentException` |
| `RuntimeException` | A stream that cannot be opened, a value that cannot be encoded |

## `Hallmark\Laravel`

| Class | Constructor and methods |
|---|---|
| `HallmarkServiceProvider` | Registers the `hallmark` log driver |
| `CreateHallmarkLogger` | `__construct(Application $app)` · `__invoke(array $config): ChannelLogger` |
| `ChannelLogger` | A PSR-3 logger · `getLogger(): Logger` · `getHandlers(): list<StackHandler>` · `getProcessors(): array` |
| `StackHandler` | `__construct(Logger $logger)`; the Monolog handler a `stack` channel holds, forwarding each record to the Hallmark logger |
| `ContextLogProcessor` | `__construct(Container $container)`; adds Laravel's Context to `extra` |

## `Hallmark\PHPUnit`

| Class | Constructor and methods |
|---|---|
| `AssertLog` (trait) | `assertLogged(TestHandler, Level, string $message)` · `assertLoggedThatContains(TestHandler, Level, string $substring)` · `assertLoggedThatMatches(TestHandler, Level, string $pattern)` · `assertLoggedThatPasses(TestHandler, Level, Closure $predicate)` · `assertNotLogged(TestHandler, Level, string $message)`, each with an optional failure message last |
| `LogRecordConstraint` | `__construct(Level $level, ?string $message = null, ?string $contains = null, ?string $pattern = null, ?Closure $predicate = null, bool $absent = false)` |
