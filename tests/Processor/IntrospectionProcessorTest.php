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

use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Processor\IntrospectionProcessor;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(IntrospectionProcessor::class)]
final class IntrospectionProcessorTest extends TestCase
{
    public function testReportsTheCallSiteThatLogged(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('app', [$handler]);
        $logger->pushProcessor(new IntrospectionProcessor());

        $logger->warning('here'); // <- the frame we expect to be reported

        $extra = $this->lastRecord($handler)->extra;

        self::assertSame(self::class, $extra['class']);
        self::assertSame('testReportsTheCallSiteThatLogged', $extra['function']);
        self::assertIsString($extra['file']);
        self::assertStringEndsWith('IntrospectionProcessorTest.php', $extra['file']);
    }

    public function testSkippedBelowConfiguredLevel(): void
    {
        $processor = new IntrospectionProcessor(Level::Error);
        $result    = $processor($this->record(level: Level::Info));

        self::assertArrayNotHasKey('file', $result->extra);
    }
}
