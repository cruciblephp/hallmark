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
use function array_unshift;

use Hallmark\LogRecord;
use Hallmark\Processor\ProcessorInterface;

/**
 * Shared implementation of {@see ProcessableHandlerInterface}: a LIFO stack of
 * processors applied to a record before the handler writes it.
 */
trait ProcessableHandlerTrait
{
    /** @var list<ProcessorInterface|callable(LogRecord): LogRecord> */
    protected array $processors = [];

    public function pushProcessor(ProcessorInterface|callable $processor): HandlerInterface
    {
        array_unshift($this->processors, $processor);

        return $this;
    }

    public function popProcessor(): callable
    {
        if ($this->processors === []) {
            throw new \LogicException('You tried to pop from an empty processor stack.');
        }

        return array_shift($this->processors);
    }

    protected function processRecord(LogRecord $record): LogRecord
    {
        foreach ($this->processors as $processor) {
            $record = $processor($record);
        }

        return $record;
    }
}
