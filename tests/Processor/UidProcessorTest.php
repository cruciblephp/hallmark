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
use Hallmark\Processor\IdGenerator;
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

    public function testPerRecordGivesEachRecordItsOwnUid(): void
    {
        $processor = new UidProcessor(16, perRecord: true);

        $first  = $processor($this->record());
        $second = $processor($this->record());

        self::assertMatchesShape('array{uid: string}', $first->extra);
        self::assertMatchesShape('array{uid: string}', $second->extra);
        self::assertSame(16, strlen($first->extra['uid']));
        self::assertNotSame($first->extra['uid'], $second->extra['uid']);
        self::assertSame($second->extra['uid'], $processor->getUid());
    }

    public function testAnIdGeneratorGivesTheApplicationsKindOfId(): void
    {
        $generator = new class implements IdGenerator {
            private int $n = 0;

            public function generate(): string
            {
                return 'id-' . ++$this->n;
            }
        };

        $processor = new UidProcessor(perRecord: true, generator: $generator);

        self::assertSame(['uid' => 'id-2'], $processor($this->record())->extra);
        self::assertSame(['uid' => 'id-3'], $processor($this->record())->extra);
    }

    public function testAnEmptyGeneratedIdIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UidProcessor(generator: new class implements IdGenerator {
            public function generate(): string
            {
                return '';
            }
        });
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
