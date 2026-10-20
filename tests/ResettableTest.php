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

use Hallmark\Handler\BufferHandler;
use Hallmark\Handler\FingersCrossedHandler;
use Hallmark\Handler\SamplingHandler;
use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Processor\CorrelationIdProcessor;
use Hallmark\Processor\UidProcessor;
use Hallmark\Resettable;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Logger::class)]
final class ResettableTest extends TestCase
{
    public function testLoggerResetFlushesBuffersAndRollsCorrelationIds(): void
    {
        $sink        = new TestHandler();
        $buffer      = new BufferHandler($sink);
        $correlation = new CorrelationIdProcessor();

        $logger = new Logger('worker', [$buffer], [$correlation]);

        $logger->warning('request 1');
        $idBefore = $correlation->getId();
        self::assertCount(0, $sink->getRecords(), 'Buffered until reset.');

        $logger->reset();

        // Buffer flushed to the sink, correlation id rolled for the next request.
        self::assertCount(1, $sink->getRecords());
        self::assertNotSame($idBefore, $correlation->getId());
    }

    public function testTheComponentsTheReadmeNamesAreResettable(): void
    {
        foreach ([BufferHandler::class, FingersCrossedHandler::class, SamplingHandler::class, UidProcessor::class, CorrelationIdProcessor::class] as $class) {
            self::assertContains(Resettable::class, class_implements($class), $class);
        }
    }

    public function testResetIgnoresNonResettableHandlers(): void
    {
        // A plain handler is not Resettable; reset() must simply skip it.
        $logger = new Logger('app', [new TestHandler(Level::Debug)]);

        $logger->reset();

        $this->expectNotToPerformAssertions();
    }
}
