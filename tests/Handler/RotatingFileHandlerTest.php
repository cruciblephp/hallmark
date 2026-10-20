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

use function glob;

use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\RotatingFileHandler;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RotatingFileHandler::class)]
final class RotatingFileHandlerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/log-rotate-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testWritesToTheFileForTheRecordsDate(): void
    {
        $handler = new RotatingFileHandler($this->dir . '/app.log');
        $handler->setFormatter(new LineFormatter(format: "%message%\n"));

        $handler->handle($this->record(message: 'then'));
        $handler->close();

        $expected = $this->dir . '/app-2026-07-28.log';

        self::assertStringEqualsFile($expected, "then\n");
    }

    public function testReplayedRecordsAcrossADayBoundaryLandInTheirOwnFiles(): void
    {
        $handler = new RotatingFileHandler($this->dir . '/app.log');
        $handler->setFormatter(new LineFormatter(format: "%message%\n"));

        $handler->handle($this->record(message: 'late', datetime: '2026-10-17T23:59:59+00:00'));
        $handler->handle($this->record(message: 'early', datetime: '2026-10-18T00:00:01+00:00'));
        $handler->handle($this->record(message: 'straggler', datetime: '2026-10-17T23:59:59+00:00'));
        $handler->close();

        self::assertStringEqualsFile($this->dir . '/app-2026-10-17.log', "late\nstraggler\n");
        self::assertStringEqualsFile($this->dir . '/app-2026-10-18.log', "early\n");
        self::assertCount(2, glob($this->dir . '/app-*.log') ?: []);
    }

    public function testCleanupKeepsOnlyMaxFiles(): void
    {
        // Seed three stale rotations, then write the record's; maxFiles = 2 should
        // leave exactly two files (the newest stale one plus the record's).
        foreach (['2026-01-01', '2026-01-02', '2026-01-03'] as $date) {
            file_put_contents($this->dir . '/app-' . $date . '.log', 'old');
        }

        $handler = new RotatingFileHandler($this->dir . '/app.log', maxFiles: 2);
        $handler->setFormatter(new LineFormatter(format: "%message%\n"));

        $handler->handle($this->record(message: 'now'));
        $handler->close();

        self::assertCount(2, glob($this->dir . '/app-*.log') ?: []);
        self::assertFileExists($this->dir . '/app-2026-01-03.log');
        self::assertFileExists($this->dir . '/app-2026-07-28.log');
    }

    public function testCleanupKeepsTheNewestFilesWhateverTheDateFormat(): void
    {
        // Day-first names sort wrongly as strings: 30-09 > 19-10 > 15-10 > 01-10.
        foreach (['30-09-2026', '01-10-2026', '15-10-2026'] as $date) {
            file_put_contents($this->dir . '/app-' . $date . '.log', 'old');
        }

        $handler = new RotatingFileHandler($this->dir . '/app.log', maxFiles: 2, dateFormat: 'd-m-Y');
        $handler->setFormatter(new LineFormatter(format: "%message%\n"));

        $handler->handle($this->record(message: 'now', datetime: '2026-10-19T12:00:00+00:00'));
        $handler->close();

        self::assertFileExists($this->dir . '/app-15-10-2026.log');
        self::assertFileExists($this->dir . '/app-19-10-2026.log');
        self::assertFileDoesNotExist($this->dir . '/app-30-09-2026.log');
        self::assertFileDoesNotExist($this->dir . '/app-01-10-2026.log');
    }

    public function testCleanupLeavesFilesItDidNotName(): void
    {
        file_put_contents($this->dir . '/app-backup.log', 'keep me');
        file_put_contents($this->dir . '/app-2026-01-01.log', 'old');

        $handler = new RotatingFileHandler($this->dir . '/app.log', maxFiles: 1);
        $handler->setFormatter(new LineFormatter(format: "%message%\n"));

        $handler->handle($this->record(message: 'now'));
        $handler->close();

        self::assertFileExists($this->dir . '/app-backup.log');
        self::assertFileExists($this->dir . '/app-2026-07-28.log');
        self::assertFileDoesNotExist($this->dir . '/app-2026-01-01.log');
    }
}
