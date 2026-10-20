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

namespace Hallmark\Tests\Examples;

use FanClub\BandEvent;
use FanClub\FanClubEvent;
use FanClub\PressEvent;
use Hallmark\Event\EventCatalog;
use Hallmark\Tests\TestCase;

/**
 * The fan-club example (examples/fan-club) runs, and prints what
 * examples/fan-club/expected-output.txt shows, dates and the checkout's path aside.
 */
final class FanClubExampleTest extends TestCase
{
    public function testTheEveningPrintsTheExpectedLog(): void
    {
        $root = \dirname(__DIR__, 2);
        $logs = \sys_get_temp_dir() . '/' . \uniqid('hallmark-fan-club-', true);

        $process = \proc_open([\PHP_BINARY, $root . '/examples/fan-club/run.php', $logs], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        $status = \proc_close($process);

        \exec('rm -rf ' . \escapeshellarg($logs));

        self::assertSame('', $stderr);
        self::assertSame(0, $status);
        self::assertStringEqualsFile($root . '/examples/fan-club/expected-output.txt', self::normalize($stdout, $root));
    }

    public function testTheCatalogIsValid(): void
    {
        self::assertSame([], EventCatalog::validate([BandEvent::class, PressEvent::class, FanClubEvent::class]));
    }

    /** Dates and the checkout's location move between runs; nothing else may. */
    public static function normalize(string $output, string $root): string
    {
        $output = \str_replace($root . '/', '', $output);

        return (string) \preg_replace('/\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}:\d{2}\.\d+[+-]\d{2}:\d{2})?/', '<date>', $output);
    }
}
