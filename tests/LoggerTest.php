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

use Hallmark\Handler\AbstractHandler;
use Hallmark\Handler\NullHandler;
use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\LogRecord;
use Hallmark\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\InvalidArgumentException;

#[CoversClass(Logger::class)]
final class LoggerTest extends TestCase
{
    public function testPsrShortcutsMapToTheRightLevel(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('app', [$handler]);

        $logger->warning('careful');
        $logger->error('broken');

        self::assertSame(Level::Warning, $handler->getRecords()[0]->level);
        self::assertSame(Level::Error, $handler->getRecords()[1]->level);
        self::assertSame('app', $handler->getRecords()[0]->channel);
    }

    public function testLogAcceptsPsrStringLevel(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('app', [$handler]);

        $logger->log('critical', 'meltdown', ['reactor' => 4]);

        self::assertSame(Level::Critical, $this->lastRecord($handler)->level);
        self::assertSame(['reactor' => 4], $this->lastRecord($handler)->context);
    }

    public function testHandlerBelowThresholdIsSkipped(): void
    {
        $handler = new TestHandler(Level::Error);
        $logger  = new Logger('app', [$handler]);

        $logger->info('quiet');

        self::assertSame([], $handler->getRecords());
    }

    public function testProcessorEnrichesExtra(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('app', [$handler]);
        $logger->pushProcessor(static fn(LogRecord $record): LogRecord => $record->with(
            extra: [...$record->extra, 'tag' => 'web'],
        ));

        $logger->warning('hello');

        self::assertSame('web', $this->lastRecord($handler)->extra['tag']);
    }

    public function testMostRecentlyPushedHandlerRunsFirstAndCanStopBubbling(): void
    {
        $bottom = new TestHandler();
        $top    = new NullHandler(Level::Warning);

        $logger = new Logger('app', [$bottom]);
        $logger->pushHandler($top);

        $logger->error('swallowed');

        // NullHandler at the top swallows the record so the bottom never sees it.
        self::assertSame([], $bottom->getRecords());
    }

    public function testPopHandlerOnEmptyStackThrows(): void
    {
        $logger = new Logger('app');

        $this->expectException(\LogicException::class);

        $logger->popHandler();
    }

    public function testAddRecordReportsWhetherHandled(): void
    {
        $logger = new Logger('app', [new TestHandler()]);

        self::assertTrue($logger->addRecord(Level::Warning, 'x'));
    }

    public function testLogRejectsAnUnknownIntegerSeverityWithThePsrException(): void
    {
        $logger = new Logger('app', [new TestHandler()]);

        $this->expectException(InvalidArgumentException::class);

        $logger->log(999, 'x');
    }

    public function testAFailingHandlerThrowsIntoTheCallerByDefault(): void
    {
        $logger = new Logger('app', [$this->failingHandler()]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disk full');

        $logger->error('x');
    }

    public function testTheExceptionHandlerReceivesAFailingHandlersExceptionAndRecord(): void
    {
        $caught = [];
        $logger = new Logger('app', [$this->failingHandler()]);
        $logger->setExceptionHandler(static function (\Throwable $e, LogRecord $record) use (&$caught): void {
            $caught[] = $e->getMessage() . ' / ' . $record->message;
        });

        $logger->error('payment failed');

        self::assertSame(['disk full / payment failed'], $caught);
    }

    public function testAChildLoggerKeepsTheExceptionHandler(): void
    {
        $caught = 0;
        $logger = new Logger('app', [$this->failingHandler()]);
        $logger->setExceptionHandler(static function () use (&$caught): void {
            $caught++;
        });

        $logger->child(['a' => 1])->error('x');

        self::assertSame(1, $caught);
    }

    private function failingHandler(): AbstractHandler
    {
        return new class extends AbstractHandler {
            public function handle(LogRecord $record): bool
            {
                throw new \RuntimeException('disk full');
            }
        };
    }
}
