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

namespace Hallmark\PHPUnit;

use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use PHPUnit\Framework\Assert;

/**
 * Drop this trait into a TestCase to assert on what a {@see TestHandler}
 * captured, with failure messages that print the records that WERE logged.
 *
 * Written against PHPUnit's API; Crucible runs it unchanged. The capture logic
 * it reads from lives in the core, in {@see TestHandler}.
 *
 *   final class CheckoutTest extends TestCase
 *   {
 *       use AssertLog;
 *
 *       public function test_it_warns_on_retry(): void
 *       {
 *           $handler = new TestHandler();
 *           (new Checkout(new Logger('shop', [$handler])))->pay();
 *
 *           $this->assertLoggedThatContains($handler, Level::Warning, 'retry');
 *       }
 *   }
 */
trait AssertLog
{
    final protected function assertLogged(TestHandler $handler, Level $level, string $message, string $failMessage = ''): void
    {
        Assert::assertThat($handler, new LogRecordConstraint($level, message: $message), $failMessage);
    }

    final protected function assertLoggedThatContains(TestHandler $handler, Level $level, string $substring, string $failMessage = ''): void
    {
        Assert::assertThat($handler, new LogRecordConstraint($level, contains: $substring), $failMessage);
    }

    final protected function assertLoggedThatMatches(TestHandler $handler, Level $level, string $pattern, string $failMessage = ''): void
    {
        Assert::assertThat($handler, new LogRecordConstraint($level, pattern: $pattern), $failMessage);
    }

    /**
     * @param \Closure(\Hallmark\LogRecord): bool $predicate
     */
    final protected function assertLoggedThatPasses(TestHandler $handler, Level $level, \Closure $predicate, string $failMessage = ''): void
    {
        Assert::assertThat($handler, new LogRecordConstraint($level, predicate: $predicate), $failMessage);
    }

    final protected function assertNotLogged(TestHandler $handler, Level $level, string $message, string $failMessage = ''): void
    {
        Assert::assertThat($handler, new LogRecordConstraint($level, message: $message, absent: true), $failMessage);
    }
}
