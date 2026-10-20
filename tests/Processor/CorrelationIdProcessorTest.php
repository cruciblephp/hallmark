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
use Hallmark\Handler\TestHandler;
use Hallmark\Logger;
use Hallmark\Processor\CorrelationIdProcessor;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CorrelationIdProcessor::class)]
final class CorrelationIdProcessorTest extends TestCase
{
    public function testStampsAStableIdUnderTheDefaultKey(): void
    {
        $processor = new CorrelationIdProcessor(length: 16);

        $first  = $processor($this->record());
        $second = $processor($this->record());

        self::assertSame(16, strlen($processor->getId()));
        self::assertSame($processor->getId(), $first->extra['correlation_id']);
        self::assertSame($first->extra['correlation_id'], $second->extra['correlation_id']);
    }

    public function testUsesAConfigurableKeyAndExplicitId(): void
    {
        $processor = new CorrelationIdProcessor(key: 'request_id', id: 'req-abc');
        $result    = $processor($this->record());

        self::assertSame('req-abc', $result->extra['request_id']);
    }

    public function testAdoptInheritsAnUpstreamId(): void
    {
        $processor = new CorrelationIdProcessor();
        $processor->adopt('trace-42');

        self::assertSame('trace-42', $processor($this->record())->extra['correlation_id']);
    }

    public function testResetStartsAFreshTrace(): void
    {
        $processor = new CorrelationIdProcessor();
        $before    = $processor->getId();

        $processor->reset();

        self::assertNotSame($before, $processor->getId());
    }

    public function testDoesNotOverwriteAPerCallValue(): void
    {
        $processor = new CorrelationIdProcessor(id: 'processor-id');
        $result    = $processor($this->record(extra: ['correlation_id' => 'call-id']));

        self::assertSame('call-id', $result->extra['correlation_id']);
    }

    public function testAnIdPassedInTheCallsContextIsTheOnlyOneOnTheRecord(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('app', [$handler], [new CorrelationIdProcessor(id: 'processor-id')]);

        $logger->info('x', ['correlation_id' => 'call-id']);

        $record = $handler->getRecords()[0];

        self::assertSame('call-id', $record->context['correlation_id']);
        self::assertArrayNotHasKey('correlation_id', $record->extra);
    }

    public function testRejectsOutOfRangeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CorrelationIdProcessor(length: 0);
    }
}
