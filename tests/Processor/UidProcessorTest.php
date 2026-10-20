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

namespace Hallmark\Tests\Processor;

use function strlen;

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Processor\UidProcessor;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(UidProcessor::class)]
final class UidProcessorTest extends TestCase
{
    public function testStampsAStableUidOfTheRequestedLength(): void
    {
        $processor = new UidProcessor(12);

        $first  = $processor($this->record());
        $second = $processor($this->record());

        self::assertSame(12, strlen($processor->getUid()));
        self::assertSame($processor->getUid(), $first->extra['uid']);
        self::assertSame($first->extra['uid'], $second->extra['uid']);
    }

    public function testResetChangesTheUid(): void
    {
        $processor = new UidProcessor();
        $before    = $processor->getUid();

        $processor->reset();

        self::assertNotSame($before, $processor->getUid());
    }

    public function testRejectsOutOfRangeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UidProcessor(0);
    }
}
