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

use Hallmark\Tests\TestCase;

/**
 * The Laravents example (examples/laravents) runs, and prints what
 * examples/laravents/expected-output.txt shows. Message ids are new on every run,
 * so each is numbered in order of appearance: the event's causation id must still
 * be the command's message id.
 */
final class LaraventsExampleTest extends TestCase
{
    public function testAHandlersLogLinesCarryLaraventsIds(): void
    {
        $root = \dirname(__DIR__, 2);
        $logs = \sys_get_temp_dir() . '/' . \uniqid('hallmark-laravents-', true);

        $process = \proc_open([\PHP_BINARY, $root . '/examples/laravents/run.php', $logs], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        $stdout = (string) \stream_get_contents($pipes[1]);
        $stderr = (string) \stream_get_contents($pipes[2]);
        $status = \proc_close($process);

        \exec('rm -rf ' . \escapeshellarg($logs));

        self::assertSame('', $stderr);
        self::assertSame(0, $status);
        self::assertStringEqualsFile($root . '/examples/laravents/expected-output.txt', self::normalize($stdout));
    }

    /** Dates move between runs, and each ULID is replaced by its order of appearance. */
    public static function normalize(string $output): string
    {
        $output = (string) \preg_replace('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d+[+-]\d{2}:\d{2}/', '<date>', $output);
        $seen   = [];

        return (string) \preg_replace_callback('/\b[0-9A-HJKMNP-TV-Z]{26}\b/', static function (array $match) use (&$seen): string {
            $seen[$match[0]] ??= '<id-' . (\count($seen) + 1) . '>';

            return $seen[$match[0]];
        }, $output);
    }
}
