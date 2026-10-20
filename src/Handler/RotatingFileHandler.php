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

namespace Hallmark\Handler;

use function array_keys;
use function array_slice;
use function arsort;
use function basename;
use function glob;
use function is_writable;
use function pathinfo;
use function strlen;
use function substr;
use function unlink;

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Level;
use Hallmark\LogRecord;

/**
 * A {@see StreamHandler} that writes each record to the file for its own date,
 * keeping at most `$maxFiles` of them.
 *
 * The file is chosen from the record's timestamp, not the wall clock, so a
 * backlog replayed across a day boundary lands in the file for each record's
 * day. `$maxFiles = 0` keeps every file forever.
 */
class RotatingFileHandler extends StreamHandler
{
    public const FILE_PER_DAY = 'Y-m-d';

    private bool $cleanupDone = false;

    public function __construct(
        private readonly string $filename,
        private readonly int $maxFiles = 0,
        Level $level = Level::Debug,
        bool $bubble = true,
        ?int $filePermission = null,
        bool $useLocking = false,
        private readonly string $dateFormat = self::FILE_PER_DAY,
    ) {
        if ($maxFiles < 0) {
            throw new InvalidArgumentException('maxFiles cannot be negative.');
        }

        parent::__construct($this->buildFilename((new \DateTimeImmutable())->format($dateFormat)), $level, $bubble, $filePermission, $useLocking);
    }

    protected function write(LogRecord $record): void
    {
        $file    = $this->buildFilename($record->datetime->format($this->dateFormat));
        $rotated = $file !== $this->url;

        if ($rotated) {
            // Release the current handle so parent::write() opens the record's file.
            $this->close();

            $this->url = $file;
        }

        parent::write($record);

        // Prune after the write so the file just written counts toward the
        // retention window. Runs on every switch of file and once at startup.
        if ($rotated || !$this->cleanupDone) {
            $this->cleanup();
            $this->cleanupDone = true;
        }
    }

    private function cleanup(): void
    {
        if ($this->maxFiles === 0) {
            return;
        }

        // Newest first by the date each name carries; a file whose date part
        // this handler could not have written is not ours to delete.
        $dated = [];

        foreach (glob($this->buildFilename('*')) ?: [] as $file) {
            $date = $this->dateOf($file);

            if ($date instanceof \DateTimeImmutable) {
                $dated[$file] = $date;
            }
        }

        arsort($dated);

        foreach (array_slice(array_keys($dated), $this->maxFiles) as $file) {
            if (is_writable($file)) {
                @unlink($file);
            }
        }
    }

    private function dateOf(string $file): ?\DateTimeImmutable
    {
        $info   = pathinfo($this->filename);
        $prefix = $info['filename'] . '-';
        $suffix = isset($info['extension']) ? '.' . $info['extension'] : '';
        $name   = basename($file);
        $part   = substr($name, strlen($prefix), strlen($name) - strlen($prefix) - strlen($suffix));
        $date   = \DateTimeImmutable::createFromFormat('!' . $this->dateFormat, $part);

        return $date !== false && $date->format($this->dateFormat) === $part ? $date : null;
    }

    private function buildFilename(string $date): string
    {
        $info = pathinfo($this->filename);
        $path = ($info['dirname'] ?? '.') . '/' . $info['filename'] . '-' . $date;

        if (isset($info['extension'])) {
            $path .= '.' . $info['extension'];
        }

        return $path;
    }
}
