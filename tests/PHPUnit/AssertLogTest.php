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

namespace Hallmark\Tests\PHPUnit;

use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\LogRecord;
use Hallmark\PHPUnit\AssertLog;
use Hallmark\PHPUnit\LogRecordConstraint;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LogRecordConstraint::class)]
final class AssertLogTest extends TestCase
{
    use AssertLog;

    private TestHandler $handler;

    private Logger $logger;

    protected function setUp(): void
    {
        $this->handler = new TestHandler();
        $this->logger  = new Logger('shop', [$this->handler]);
    }

    public function testAssertsOnCapturedRecords(): void
    {
        $this->logger->warning('payment retry scheduled');
        $this->logger->error('gateway declined', ['code' => 402]);

        $this->assertLogged($this->handler, Level::Warning, 'payment retry scheduled');
        $this->assertLoggedThatContains($this->handler, Level::Warning, 'retry');
        $this->assertLoggedThatMatches($this->handler, Level::Error, '/declined/');
        $this->assertLoggedThatPasses(
            $this->handler,
            Level::Error,
            static fn(LogRecord $record): bool => ($record->context['code'] ?? null) === 402,
        );
        $this->assertNotLogged($this->handler, Level::Critical, 'gateway declined');
    }

    public function testFailureListsWhatWasLogged(): void
    {
        $this->logger->info('all good');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessageMatches('/logged an ERROR record whose message contains "boom".*INFO: all good/s');

        $this->assertLoggedThatContains($this->handler, Level::Error, 'boom');
    }

    public function testNotLoggedFailureSaysWhatShouldNotHaveBeenLogged(): void
    {
        $this->logger->info('all good');

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('did not log an INFO record with message "all good"');

        $this->assertNotLogged($this->handler, Level::Info, 'all good');
    }
}
