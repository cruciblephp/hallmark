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

namespace Hallmark\Tests;

use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\LogRecord;
use LucianoPereira\Crucible\Framework\TestCase as BaseTestCase;

/**
 * Shared base with a factory for records, so individual tests state only the
 * fields they care about.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @param array<array-key, mixed> $context
     * @param array<array-key, mixed> $extra
     */
    protected function record(
        Level $level = Level::Warning,
        string $message = 'test',
        array $context = [],
        array $extra = [],
        string $channel = 'test',
        string $datetime = '2026-07-28T12:00:00+00:00',
    ): LogRecord {
        return new LogRecord(
            datetime: new \DateTimeImmutable($datetime),
            channel:  $channel,
            level:    $level,
            message:  $message,
            context:  $context,
            extra:    $extra,
        );
    }

    /** The most recent record the handler captured; fails the test when there is none. */
    protected function lastRecord(TestHandler $handler): LogRecord
    {
        $records = $handler->getRecords();

        self::assertNotEmpty($records, 'Nothing was logged.');

        return $records[array_key_last($records)];
    }
}
