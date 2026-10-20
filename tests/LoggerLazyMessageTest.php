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

use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Logger::class)]
final class LoggerLazyMessageTest extends TestCase
{
    public function testClosureMessageIsNotEvaluatedForADisabledLevel(): void
    {
        $calls  = 0;
        $logger = new Logger('app', [new TestHandler(Level::Error)]);

        $logger->debug(function () use (&$calls): string {
            $calls++;

            return 'expensive';
        });

        self::assertSame(0, $calls, 'The closure must not run when no handler accepts the level.');
    }

    public function testClosureMessageIsEvaluatedAndUsedWhenHandled(): void
    {
        $calls   = 0;
        $handler = new TestHandler();
        $logger  = new Logger('app', [$handler]);

        $logger->error(function () use (&$calls): string {
            $calls++;

            return 'evaluated';
        });

        self::assertSame(1, $calls);
        self::assertSame('evaluated', $this->lastRecord($handler)->message);
    }
}
