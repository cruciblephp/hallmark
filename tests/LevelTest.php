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

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

#[CoversClass(Level::class)]
final class LevelTest extends TestCase
{
    public function testSeverityOrderingIsMonotonic(): void
    {
        $previous = null;

        foreach (Level::cases() as $level) {
            if ($previous !== null) {
                self::assertTrue($level->isHigherThan($previous), $level->name . ' ranks above ' . $previous->name);
            }

            $previous = $level;
        }

        self::assertTrue(Level::Error->isHigherThan(Level::Warning));
        self::assertTrue(Level::Debug->isLowerThan(Level::Info));
    }

    public function testIncludesActsAsAThreshold(): void
    {
        self::assertTrue(Level::Warning->includes(Level::Error));
        self::assertTrue(Level::Warning->includes(Level::Warning));
        self::assertFalse(Level::Warning->includes(Level::Info));
    }

    #[DataProvider('psrLevels')]
    public function testRoundTripsThroughPsrStrings(string $psr, Level $expected): void
    {
        self::assertSame($expected, Level::fromPsr($psr));
        self::assertSame($psr, $expected->toPsr());
    }

    public function testFromPsrAcceptsAnExistingLevel(): void
    {
        self::assertSame(Level::Alert, Level::fromPsr(Level::Alert));
    }

    public function testFromPsrRejectsUnknownLevel(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Level::fromPsr('verbose');
    }

    public function testNameRoundTrip(): void
    {
        self::assertSame('WARNING', Level::Warning->getName());
        self::assertSame(Level::Warning, Level::fromName('warning'));
    }

    public function testRfc5424Mapping(): void
    {
        self::assertSame(0, Level::Emergency->toRfc5424());
        self::assertSame(7, Level::Debug->toRfc5424());
    }

    /**
     * @return iterable<string, array{string, Level}>
     */
    public static function psrLevels(): iterable
    {
        yield 'debug'     => [LogLevel::DEBUG, Level::Debug];
        yield 'info'      => [LogLevel::INFO, Level::Info];
        yield 'notice'    => [LogLevel::NOTICE, Level::Notice];
        yield 'warning'   => [LogLevel::WARNING, Level::Warning];
        yield 'error'     => [LogLevel::ERROR, Level::Error];
        yield 'critical'  => [LogLevel::CRITICAL, Level::Critical];
        yield 'alert'     => [LogLevel::ALERT, Level::Alert];
        yield 'emergency' => [LogLevel::EMERGENCY, Level::Emergency];
    }
}
