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

namespace Hallmark\Processor;

use Hallmark\LogRecord;

/**
 * Transforms a record before it reaches the handlers — typically to add
 * `extra` metadata. A processor must be pure: return a new record (via
 * {@see LogRecord::with()}) rather than mutating the one it is given.
 */
interface ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord;
}
