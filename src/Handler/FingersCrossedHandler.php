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

namespace Hallmark\Handler;

use function array_shift;
use function count;

use Hallmark\Level;
use Hallmark\LogRecord;
use Hallmark\Resettable;

/**
 * Buffers everything silently until a record of `$activationLevel` or higher
 * appears, then flushes the whole buffer to the wrapped handler and stays open.
 *
 * The payoff: a healthy request writes nothing, but the moment something goes
 * wrong you get the full debug-level lead-up to the error — the context you
 * always wish you had logged. If no trigger fires, the buffer is discarded on
 * close, so normal traffic costs zero I/O.
 */
final class FingersCrossedHandler implements HandlerInterface, Resettable
{
    /** @var list<LogRecord> */
    private array $buffer = [];

    private bool $activated = false;

    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly Level $activationLevel = Level::Warning,
        private readonly int $bufferSize = 0,
    ) {}

    /** Buffers every level, so the fast path must always build the record. */
    public function getLevel(): Level
    {
        return Level::Debug;
    }

    public function isHandling(LogRecord $record): bool
    {
        return true;
    }

    public function handle(LogRecord $record): bool
    {
        if ($this->activated) {
            return $this->handler->handle($record);
        }

        $this->buffer[] = $record;

        if ($this->bufferSize > 0 && count($this->buffer) > $this->bufferSize) {
            array_shift($this->buffer);
        }

        if ($record->level->value >= $this->activationLevel->value) {
            $this->activate();
        }

        return false;
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    /** Forget the buffer and disarm, e.g. between requests on a long-lived worker. */
    public function reset(): void
    {
        $this->buffer    = [];
        $this->activated = false;
    }

    public function close(): void
    {
        // A non-activated buffer is intentionally dropped: no trigger, no output.
        $this->buffer = [];
        $this->handler->close();
    }

    private function activate(): void
    {
        $this->activated = true;
        $this->handler->handleBatch($this->buffer);
        $this->buffer = [];
    }
}
