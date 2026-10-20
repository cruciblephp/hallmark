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

namespace Hallmark\Formatter;

use function implode;

use Hallmark\LogRecord;

/**
 * Serializes each record as a single JSON object — the natural feed for
 * structured log stores (ELK, Loki, CloudWatch) and anything that ingests
 * newline-delimited JSON.
 */
final class JsonFormatter extends NormalizerFormatter
{
    public function __construct(
        private readonly BatchMode $batchMode = BatchMode::Json,
        private readonly bool $appendNewline = true,
        ?string $dateFormat = null,
        private readonly bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = true,
    ) {
        parent::__construct($dateFormat);
        $this->includeStacktraces($includeStacktraces);
    }

    public function format(LogRecord $record): string
    {
        return $this->toJson($this->normalizeRecord($record), true) . ($this->appendNewline ? "\n" : '');
    }

    public function formatBatch(array $records): string
    {
        return match ($this->batchMode) {
            BatchMode::Json     => $this->formatBatchJson($records),
            BatchMode::Newlines => $this->formatBatchNewlines($records),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRecord(LogRecord $record): array
    {
        $normalized = [
            'message'    => $record->message,
            'level'      => $record->level->value,
            'level_name' => $record->level->getName(),
            'channel'    => $record->channel,
            'datetime'   => $this->formatRecordDate($record->datetime),
        ];

        foreach (['context' => $record->context, 'extra' => $record->extra] as $bag => $values) {
            if ($values !== []) {
                $normalized[$bag] = $this->normalize($values);
            } elseif (!$this->ignoreEmptyContextAndExtra) {
                // An empty bag is an object on the wire, as it is when populated.
                $normalized[$bag] = new \stdClass();
            }
        }

        return $normalized;
    }

    /**
     * @param list<LogRecord> $records
     */
    private function formatBatchJson(array $records): string
    {
        $normalized = [];

        foreach ($records as $record) {
            $normalized[] = $this->normalizeRecord($record);
        }

        return $this->toJson($normalized, true);
    }

    /**
     * @param list<LogRecord> $records
     */
    private function formatBatchNewlines(array $records): string
    {
        $lines = [];

        foreach ($records as $record) {
            $lines[] = $this->toJson($this->normalizeRecord($record), true);
        }

        return implode("\n", $lines);
    }
}
