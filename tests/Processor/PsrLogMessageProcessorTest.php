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

use Hallmark\Processor\PsrLogMessageProcessor;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(PsrLogMessageProcessor::class)]
final class PsrLogMessageProcessorTest extends TestCase
{
    public function testInterpolatesPlaceholders(): void
    {
        $processor = new PsrLogMessageProcessor();
        $result    = $processor($this->record(
            message: 'User {id} did {action}',
            context: ['id' => 42, 'action' => 'login'],
        ));

        self::assertSame('User 42 did login', $result->message);
    }

    public function testLeavesMessageWithoutBracesUntouched(): void
    {
        $processor = new PsrLogMessageProcessor();
        $record    = $this->record(message: 'nothing here', context: ['id' => 1]);

        self::assertSame($record, $processor($record));
    }

    public function testCanRemoveUsedContextFields(): void
    {
        $processor = new PsrLogMessageProcessor(removeUsedContextFields: true);
        $result    = $processor($this->record(
            message: 'hi {name}',
            context: ['name' => 'Sam', 'keep' => true],
        ));

        self::assertSame('hi Sam', $result->message);
        self::assertSame(['keep' => true], $result->context);
    }

    public function testRepeatedTemplateInterpolatesConsistently(): void
    {
        // Exercises the placeholder cache: the same template logged repeatedly
        // must interpolate identically each time.
        $processor = new PsrLogMessageProcessor();

        for ($i = 0; $i < 3; $i++) {
            $result = $processor($this->record(message: 'item {n} of {total}', context: ['n' => $i, 'total' => 3]));
            self::assertSame("item {$i} of 3", $result->message);
        }
    }

    public function testDottedPlaceholderNamesAreSupported(): void
    {
        $processor = new PsrLogMessageProcessor();
        $result    = $processor($this->record(message: '{user.id} acted', context: ['user.id' => 7]));

        self::assertSame('7 acted', $result->message);
    }

    public function testStringifiesNonScalarValues(): void
    {
        $processor = new PsrLogMessageProcessor(dateFormat: 'Y-m-d');
        $result    = $processor($this->record(
            message: '{when} {list} {nothing}',
            context: [
                'when'    => new \DateTimeImmutable('2026-07-28T00:00:00+00:00'),
                'list'    => ['a', 'b'],
                'nothing' => null,
            ],
        ));

        self::assertSame('2026-07-28 ["a","b"] ', $result->message);
    }

    public function testDatesUseAnIsoFormatWhenNoneIsGiven(): void
    {
        $processor = new PsrLogMessageProcessor();
        $result    = $processor($this->record(
            message: 'at {when}',
            context: ['when' => new \DateTimeImmutable('2026-07-28T00:00:00+00:00')],
        ));

        self::assertSame('at 2026-07-28T00:00:00.000000+00:00', $result->message);
    }
}
