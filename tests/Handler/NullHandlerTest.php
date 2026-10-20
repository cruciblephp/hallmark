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

use Hallmark\Handler\NullHandler;
use Hallmark\Level;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullHandler::class)]
final class NullHandlerTest extends TestCase
{
    public function testSwallowsRecordsAtOrAboveItsLevel(): void
    {
        $handler = new NullHandler(Level::Warning);

        self::assertTrue($handler->handle($this->record(level: Level::Error)));
        self::assertTrue($handler->handle($this->record(level: Level::Warning)));
    }

    public function testLetsLowerRecordsBubble(): void
    {
        $handler = new NullHandler(Level::Warning);

        self::assertFalse($handler->handle($this->record(level: Level::Info)));
    }
}
