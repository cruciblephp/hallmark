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

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Level;
use Hallmark\LogRecord;
use Hallmark\Resettable;

/**
 * Wraps another handler and forwards only one record in every `$factor`, so a
 * chatty code path survives a flood without drowning the sink — the sampling
 * strategy zap and OpenTelemetry use.
 *
 * Sampling is deterministic (every Nth record), which keeps it cheap and makes
 * test output predictable. Dropped records still bubble to lower handlers.
 */
final class SamplingHandler implements HandlerInterface, Resettable
{
    private int $counter = 0;

    public function __construct(
        private readonly HandlerInterface $handler,
        private readonly int $factor,
    ) {
        if ($factor < 1) {
            throw new InvalidArgumentException('The sampling factor must be at least 1.');
        }
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

        $this->counter++;

        if ($this->counter % $this->factor === 0) {
            return $this->handler->handle($record);
        }

        return false;
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    /** Restart the sampling cycle from the next record. */
    public function reset(): void
    {
        $this->counter = 0;
    }

    public function close(): void
    {
        $this->handler->close();
    }
}
