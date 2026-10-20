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
 * Buffers records in memory and flushes them to the wrapped handler in one batch
 * — on {@see self::flush()}, on {@see self::close()}, or when the buffer is full.
 *
 * Collapsing many writes into one batch is the cheap way to cut I/O syscalls in
 * a request-scoped runtime that has no background logging thread. `$bufferLimit`
 * of 0 buffers until flush; a positive limit either flushes on overflow or drops
 * the oldest record to stay bounded.
 */
final class BufferHandler implements HandlerInterface, Resettable
{
    /** @var list<LogRecord> */
    private array $buffer = [];

    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly int $bufferLimit = 0,
        private readonly bool $flushOnOverflow = true,
    ) {}

    public function __destruct()
    {
        $this->flush();
    }

    public function getLevel(): Level
    {
        return $this->handler->getLevel();
    }

    public function isHandling(LogRecord $record): bool
    {
        return $this->handler->isHandling($record);
    }

    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if ($this->bufferLimit > 0 && count($this->buffer) >= $this->bufferLimit) {
            if ($this->flushOnOverflow) {
                $this->flush();
            } else {
                array_shift($this->buffer);
            }
        }

        $this->buffer[] = $record;

        return false;
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    /** Send everything buffered to the wrapped handler as one batch, then reset. */
    public function flush(): void
    {
        if ($this->buffer === []) {
            return;
        }

        $this->handler->handleBatch($this->buffer);
        $this->buffer = [];
    }

    /** Flush anything pending so the next request starts with an empty buffer. */
    public function reset(): void
    {
        $this->flush();
    }

    public function close(): void
    {
        $this->flush();
        $this->handler->close();
    }
}
