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

use Hallmark\LogRecord;
use Hallmark\Processor\ProcessorInterface;

/**
 * A handler that can enrich records with its own processor stack, independent
 * of the logger-level processors.
 */
interface ProcessableHandlerInterface extends HandlerInterface
{
    /**
     * @param ProcessorInterface|callable(LogRecord): LogRecord $processor
     */
    public function pushProcessor(ProcessorInterface|callable $processor): HandlerInterface;

    /**
     * @return ProcessorInterface|callable(LogRecord): LogRecord
     */
    public function popProcessor(): callable;
}
