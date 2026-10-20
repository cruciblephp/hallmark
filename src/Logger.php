<?php

declare(strict_types=1);
/*
 * This file is part of the Hallmark library.
 *
 * (c) 2026 Luciano Federico Pereira
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Hallmark;

use function array_shift;
use function array_unshift;
use function array_values;
use function date_default_timezone_get;
use function is_int;
use function is_string;

use Hallmark\Event\EventCode;
use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Handler\HandlerInterface;
use Hallmark\Processor\ProcessorInterface;
use Psr\Log\LoggerInterface;

/**
 * A PSR-3 logger that dispatches each event through an ordered stack of
 * handlers, after enriching it with a stack of processors.
 *
 * Both stacks are LIFO: the most recently pushed handler is consulted first,
 * and a handler that reports it "handled" the record (by returning `true`,
 * i.e. it does not bubble) stops propagation to the handlers below it.
 *
 * @phpstan-type ProcessorCallable ProcessorInterface|callable(LogRecord): LogRecord
 */
final class Logger implements LoggerInterface
{
    /** @var list<HandlerInterface> */
    private array $handlers;

    /** @var list<ProcessorInterface|callable(LogRecord): LogRecord> */
    private array $processors;

    private readonly \DateTimeZone $timezone;

    /** @var (\Closure(\Throwable, LogRecord): void)|null */
    private ?\Closure $exceptionHandler = null;

    /**
     * @param array<HandlerInterface>                                  $handlers     consulted first-to-last
     * @param array<ProcessorInterface|callable(LogRecord): LogRecord> $processors   applied first-to-last
     * @param array<array-key, mixed>                                  $boundContext merged into every record (see {@see self::child()})
     */
    public function __construct(
        private readonly string $name,
        array $handlers = [],
        array $processors = [],
        ?\DateTimeZone $timezone = null,
        private readonly array $boundContext = [],
    ) {
        $this->handlers     = array_values($handlers);
        $this->processors   = array_values($processors);
        $this->timezone     = $timezone ?? new \DateTimeZone(date_default_timezone_get());
    }

    /**
     * A new logger that shares this one's handler and processor stacks but
     * carries additional context merged into every record it emits — the
     * "child logger" pattern from Pino/Bunyan/Serilog.
     *
     * The child's stacks are independent copies (pushing a handler on the child
     * does not touch the parent), while the handler *instances* are shared, so
     * output still goes to the same destinations. Per-call context overrides
     * bound context on a key clash.
     *
     *   $requestLog = $log->child(['request_id' => $id]);
     *   $requestLog->info('handling');   // ... {"request_id":"..."} on every line
     *
     * @param array<array-key, mixed> $context
     */
    public function child(array $context = [], ?string $name = null): self
    {
        $child = new self(
            $name ?? $this->name,
            $this->handlers,
            $this->processors,
            $this->timezone,
            [...$this->boundContext, ...$context],
        );
        $child->exceptionHandler = $this->exceptionHandler;

        return $child;
    }

    /** The channel name stamped onto every record this logger produces. */
    public function name(): string
    {
        return $this->name;
    }

    public function pushHandler(HandlerInterface $handler): self
    {
        array_unshift($this->handlers, $handler);

        return $this;
    }

    public function popHandler(): HandlerInterface
    {
        if ($this->handlers === []) {
            throw new \LogicException('You tried to pop from an empty handler stack.');
        }

        return array_shift($this->handlers);
    }

    /** @return list<HandlerInterface> */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * @param ProcessorInterface|callable(LogRecord): LogRecord $processor
     */
    public function pushProcessor(ProcessorInterface|callable $processor): self
    {
        array_unshift($this->processors, $processor);

        return $this;
    }

    /**
     * @return ProcessorInterface|callable(LogRecord): LogRecord
     */
    public function popProcessor(): callable
    {
        if ($this->processors === []) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    /** @return list<ProcessorInterface|callable(LogRecord): LogRecord> */
    public function getProcessors(): array
    {
        return $this->processors;
    }

    /**
     * Receive what a handler or processor throws instead of letting it reach the
     * code that logged; `null` restores the default of rethrowing.
     *
     * @param (\Closure(\Throwable, LogRecord): void)|null $callback
     */
    public function setExceptionHandler(?\Closure $callback): self
    {
        $this->exceptionHandler = $callback;

        return $this;
    }

    /** @return (\Closure(\Throwable, LogRecord): void)|null */
    public function getExceptionHandler(): ?\Closure
    {
        return $this->exceptionHandler;
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function emergency(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Emergency, $message, $context);
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function alert(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Alert, $message, $context);
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function critical(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Critical, $message, $context);
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function error(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Error, $message, $context);
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function warning(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Warning, $message, $context);
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function notice(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Notice, $message, $context);
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function info(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Info, $message, $context);
    }

    /**
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function debug(string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord(Level::Debug, $message, $context);
    }

    /**
     * Log a catalogued {@see EventCode}. Severity and message come from the event
     * itself — so they cannot disagree with the code — and the code is stamped
     * into the context under `event_code` for querying and grouping downstream.
     *
     * @param array<array-key, mixed> $context
     */
    public function event(EventCode $event, array $context = []): void
    {
        $this->addRecord(
            $event->level(),
            $event->message(),
            [...$context, 'event_code' => $event->code()],
        );
    }

    /**
     * PSR-3 entry point. `$level` may be a {@see LogLevel} string, a raw integer
     * severity, or a {@see Level} case — all funnel to {@see self::addRecord()}.
     *
     * `$message` may also be a `Closure(): string|\Stringable` that is evaluated
     * only if a handler will consume the record — so an expensive message never
     * runs for a disabled level.
     *
     * @param \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param array<array-key, mixed>                             $context
     */
    public function log(mixed $level, string|\Stringable|\Closure $message, array $context = []): void
    {
        $this->addRecord($this->toLevel($level), $message, $context);
    }

    /**
     * Build a record and offer it to each handler in turn. Processors run at
     * most once, and only once a handler has signalled interest — so a stack of
     * silent handlers never pays for enrichment it will not use.
     *
     * @param  \Closure(): (string|\Stringable)|string|\Stringable $message
     * @param  array<array-key, mixed>                             $context
     * @return bool                                                whether any handler handled the record
     */
    public function addRecord(Level $level, string|\Stringable|\Closure $message, array $context = [], ?\DateTimeImmutable $datetime = null): bool
    {
        // Fast path: if no handler's threshold accepts this level, return before
        // allocating a DateTimeImmutable, a LogRecord, or running any processor —
        // and before resolving a lazy message closure. This is what makes a
        // disabled level (e.g. debug() in production) nearly free. getLevel() is a
        // documented lower bound, so this never skips a record a handler's
        // isHandling() would have accepted.
        if (!$this->isHandling($level)) {
            return false;
        }

        if ($this->boundContext !== []) {
            // Bound context is the base; a per-call key of the same name wins.
            $context = [...$this->boundContext, ...$context];
        }

        $record = new LogRecord(
            datetime: $datetime ?? new \DateTimeImmutable('now', $this->timezone),
            channel:  $this->name,
            level:    $level,
            message:  $message instanceof \Closure ? (string) $message() : (string) $message,
            context:  $context,
        );

        $handled           = false;
        $recordInitialized = $this->processors === [];

        try {
            foreach ($this->handlers as $handler) {
                if (!$handler->isHandling($record)) {
                    continue;
                }

                // At least one handler is interested — the record counts as handled
                // regardless of whether a later one stops it bubbling.
                $handled = true;

                if (!$recordInitialized) {
                    foreach ($this->processors as $processor) {
                        $record = $processor($record);
                    }
                    $recordInitialized = true;
                }

                if ($handler->handle($record) === true) {
                    break;
                }
            }
        } catch (\Throwable $e) {
            if (!$this->exceptionHandler instanceof \Closure) {
                throw $e;
            }

            ($this->exceptionHandler)($e, $record);
        }

        return $handled;
    }

    /** Whether any handler in the stack would act on a record of this level. */
    public function isHandling(Level $level): bool
    {
        $value = $level->value;

        foreach ($this->handlers as $handler) {
            if ($value >= $handler->getLevel()->value) {
                return true;
            }
        }

        return false;
    }

    /** Close every handler, flushing any buffered state. */
    public function close(): void
    {
        foreach ($this->handlers as $handler) {
            $handler->close();
        }
    }

    /**
     * Return every {@see Resettable} handler and processor to a clean per-request
     * state. Call this at a request/job boundary on a long-lived worker so
     * buffered records and correlation ids do not bleed across requests.
     */
    public function reset(): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof Resettable) {
                $handler->reset();
            }
        }

        foreach ($this->processors as $processor) {
            if ($processor instanceof Resettable) {
                $processor->reset();
            }
        }
    }

    private function toLevel(mixed $level): Level
    {
        return match (true) {
            $level instanceof Level => $level,
            is_int($level)          => Level::tryFrom($level) ?? throw new InvalidArgumentException('Unknown log level severity: ' . $level),
            is_string($level)       => Level::fromPsr($level),
            default                 => throw new InvalidArgumentException('Level must be a Level, an int severity or a PSR-3 level string.'),
        };
    }
}
