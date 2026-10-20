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

/**
 * How {@see JsonFormatter::formatBatch()} lays out several records.
 */
enum BatchMode
{
    /** One JSON array holding every record. */
    case Json;

    /** Newline-delimited JSON, one object per line. */
    case Newlines;
}
