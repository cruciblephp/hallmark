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

namespace Hallmark\Tests\Handler;

use Hallmark\Handler\BufferHandler;
use Hallmark\Handler\FingersCrossedHandler;
use Hallmark\Handler\SamplingHandler;
use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamplingHandler::class)]
#[CoversClass(BufferHandler::class)]
#[CoversClass(FingersCrossedHandler::class)]
final class DecoratorHandlersTest extends TestCase
{
    public function testSamplingForwardsEveryNthRecord(): void
    {
        $inner   = new TestHandler();
        $sampler = new SamplingHandler($inner, 3);

        for ($i = 1; $i <= 9; $i++) {
            $sampler->handle($this->warning());
        }

        // 3rd, 6th, 9th forwarded.
        self::assertCount(3, $inner->getRecords());
    }

    public function testBufferHoldsUntilFlush(): void
    {
        $inner  = new TestHandler();
        $buffer = new BufferHandler($inner);

        $buffer->handle($this->warning());
        $buffer->handle($this->warning());
        self::assertCount(0, $inner->getRecords(), 'Nothing written before flush.');

        $buffer->flush();
        self::assertCount(2, $inner->getRecords());
    }

    public function testBufferFlushesOnOverflow(): void
    {
        $inner  = new TestHandler();
        $buffer = new BufferHandler($inner, bufferLimit: 2, flushOnOverflow: true);

        $buffer->handle($this->warning());
        $buffer->handle($this->warning());
        $buffer->handle($this->warning()); // triggers flush of the first two

        self::assertCount(2, $inner->getRecords());
    }

    public function testFingersCrossedStaysSilentWithoutATrigger(): void
    {
        $inner = new TestHandler();
        $fch   = new FingersCrossedHandler($inner, Level::Error);

        $fch->handle($this->record(Level::Info));
        $fch->handle($this->record(Level::Debug));
        $fch->close();

        self::assertCount(0, $inner->getRecords());
    }

    public function testFingersCrossedFlushesBufferOnTrigger(): void
    {
        $inner = new TestHandler();
        $fch   = new FingersCrossedHandler($inner, Level::Error);

        $fch->handle($this->record(Level::Info, 'lead-up 1'));
        $fch->handle($this->record(Level::Debug, 'lead-up 2'));
        $fch->handle($this->record(Level::Error, 'the failure')); // triggers flush

        // All three (buffered context + the trigger) reach the sink.
        self::assertCount(3, $inner->getRecords());
        self::assertSame('lead-up 1', $inner->getRecords()[0]->message);

        // After activation it passes through live.
        $fch->handle($this->record(Level::Info, 'after'));
        self::assertCount(4, $inner->getRecords());
    }

    private function warning(): \Hallmark\LogRecord
    {
        return $this->record(Level::Warning);
    }

    private function record(Level $level, string $message = 'x'): \Hallmark\LogRecord
    {
        return new \Hallmark\LogRecord(
            datetime: new \DateTimeImmutable('2026-07-28T12:00:00+00:00'),
            channel:  'test',
            level:    $level,
            message:  $message,
        );
    }
}
