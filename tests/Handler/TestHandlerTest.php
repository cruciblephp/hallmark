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

use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TestHandler::class)]
final class TestHandlerTest extends TestCase
{
    public function testCapturesRecordsAndQueriesThem(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('test', [$handler]);

        $logger->info('user signed in');
        $logger->error('connection timeout after 30s');

        self::assertCount(2, $handler->getRecords());
        self::assertTrue($handler->hasRecords(Level::Error));
        self::assertTrue($handler->hasRecord(Level::Info, 'user signed in'));
        self::assertTrue($handler->hasErrorThatContains('timeout'));
        self::assertFalse($handler->hasWarningThatContains('timeout'));
    }

    public function testClearEmptiesCapturedRecords(): void
    {
        $handler = new TestHandler();
        $handler->handle($this->record());

        $handler->clear();

        self::assertSame([], $handler->getRecords());
    }
}
