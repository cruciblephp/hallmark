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

namespace Hallmark\Processor;

use function memory_get_usage;
use function round;

use Hallmark\LogRecord;

/**
 * Adds the current PHP memory usage to `extra.memory_usage` — a human string
 * like `1.53 MB` by default, or raw bytes when `$useFormatting` is off.
 */
final readonly class MemoryUsageProcessor implements ProcessorInterface
{
    public function __construct(
        private bool $realUsage = true,
        private bool $useFormatting = true,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $bytes = memory_get_usage($this->realUsage);

        return $record->with(extra: [
            ...$record->extra,
            'memory_usage' => $this->useFormatting ? $this->format($bytes) : $bytes,
        ]);
    }

    private function format(int $bytes): string
    {
        if ($bytes > 1024 * 1024) {
            return round($bytes / 1024 / 1024, 2) . ' MB';
        }

        if ($bytes > 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }
}
