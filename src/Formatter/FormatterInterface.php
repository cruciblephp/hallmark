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

namespace Hallmark\Formatter;

use Hallmark\LogRecord;

/**
 * Turns a {@see LogRecord} into the string a handler writes out.
 */
interface FormatterInterface
{
    public function format(LogRecord $record): string;

    /**
     * @param list<LogRecord> $records
     */
    public function formatBatch(array $records): string;
}
