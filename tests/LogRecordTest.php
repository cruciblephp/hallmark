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

use Hallmark\Level;
use Hallmark\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LogRecord::class)]
final class LogRecordTest extends TestCase
{
    public function testWithReturnsAModifiedCopyAndLeavesOriginalUntouched(): void
    {
        $original = $this->record(extra: ['a' => 1]);
        $modified = $original->with(extra: ['a' => 1, 'b' => 2]);

        self::assertNotSame($original, $modified);
        self::assertSame(['a' => 1], $original->extra);
        self::assertSame(['a' => 1, 'b' => 2], $modified->extra);
        self::assertSame($original->message, $modified->message);
    }

    public function testWithoutArgumentsPreservesEveryField(): void
    {
        $record = $this->record(level: Level::Error, message: 'boom', context: ['x' => 1]);
        $copy   = $record->with();

        self::assertEquals($record, $copy);
    }

    public function testToArrayShape(): void
    {
        $record = $this->record(level: Level::Notice, message: 'hi', context: ['k' => 'v']);
        $array  = $record->toArray();

        self::assertSame('hi', $array['message']);
        self::assertSame(250, $array['level']);
        self::assertSame('NOTICE', $array['level_name']);
        self::assertSame(['k' => 'v'], $array['context']);
        self::assertSame('test', $array['channel']);
    }
}
