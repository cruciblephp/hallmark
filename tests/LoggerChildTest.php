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
use Hallmark\Logger;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Logger::class)]
final class LoggerChildTest extends TestCase
{
    public function testChildMergesBoundContextIntoEveryRecord(): void
    {
        $handler = new TestHandler();
        $child   = (new Logger('app', [$handler]))->child(['request_id' => 'abc']);

        $child->warning('handling', ['step' => 1]);

        self::assertSame('abc', $this->lastRecord($handler)->context['request_id']);
        self::assertSame(1, $this->lastRecord($handler)->context['step']);
    }

    public function testPerCallContextOverridesBoundContext(): void
    {
        $handler = new TestHandler();
        $child   = (new Logger('app', [$handler]))->child(['scope' => 'parent']);

        $child->warning('x', ['scope' => 'call']);

        self::assertSame('call', $this->lastRecord($handler)->context['scope']);
    }

    public function testChildSharesHandlersButHasAnIndependentStack(): void
    {
        $handler = new TestHandler();
        $parent  = new Logger('app', [$handler]);
        $child   = $parent->child(['a' => 1]);

        $child->pushHandler(new TestHandler());

        // Pushing on the child must not grow the parent's stack...
        self::assertCount(1, $parent->getHandlers());
        // ...but the shared handler still receives the child's records.
        $child->warning('shared');
        self::assertSame('shared', $this->lastRecord($handler)->message);
    }

    public function testChildCanRenameTheChannel(): void
    {
        $handler = new TestHandler();
        $child   = (new Logger('app', [$handler]))->child([], 'worker');

        $child->warning('x');

        self::assertSame('worker', $this->lastRecord($handler)->channel);
    }
}
