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

use Hallmark\Level;
use Hallmark\LogRecord;

/**
 * A destination for log records.
 *
 * The logger asks {@see self::isHandling()} first (a cheap level check) and only
 * calls {@see self::handle()} when that returns true. `handle()` returns whether
 * the record was fully consumed: `true` stops the record bubbling to lower
 * handlers, `false` lets it continue.
 */
interface HandlerInterface
{
    /**
     * The minimum severity this handler will ever act on. The logger reads this
     * to skip building a record entirely when no handler wants the level, so it
     * must be a lower bound: {@see self::isHandling()} may be stricter, never
     * looser.
     */
    public function getLevel(): Level;

    /** Whether this handler is interested in a record of this level at all. */
    public function isHandling(LogRecord $record): bool;

    /** Process one record; return true to stop it bubbling to lower handlers. */
    public function handle(LogRecord $record): bool;

    /**
     * Process many records at once.
     *
     * @param list<LogRecord> $records
     */
    public function handleBatch(array $records): void;

    /** Release any held resource (file handle, socket, syslog connection). */
    public function close(): void;
}
