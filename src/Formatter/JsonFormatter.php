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

use function array_filter;
use function implode;
use function is_array;
use function is_object;
use function is_scalar;

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
        bool $includeStacktraces = false,
    ) {
        parent::__construct($dateFormat);
        $this->includeStacktraces($includeStacktraces);
    }

    public function format(LogRecord $record): string
    {
        return $this->toJson($this->normalizeRecord($record), true) . ($this->appendNewline ? "\n" : '');
    }

    /**
     * As Monolog's JSON: a value the encoder can write is left to it, so a NaN
     * becomes `0`, a `JsonSerializable` is serialized as it says, and an object's
     * public properties are written as they are.
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        if ($data === null || is_scalar($data)) {
            return $data;
        }

        if ($depth > $this->maxNormalizeDepth) {
            return 'Over ' . $this->maxNormalizeDepth . ' levels deep, aborting normalization';
        }

        if (is_array($data)) {
            return $this->normalizeArray($data, $depth);
        }

        if (!is_object($data)) {
            return parent::normalize($data, $depth);
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format($this->dateFormat);
        }

        if ($data instanceof \Throwable) {
            return $this->normalizeException($data, $depth);
        }

        if ($data instanceof \JsonSerializable) {
            return $data;
        }

        if ($data instanceof \Stringable) {
            try {
                return $data->__toString();
            } catch (\Throwable) {
                return $data::class;
            }
        }

        if ($data instanceof \__PHP_Incomplete_Class) {
            return new \ArrayObject($data);
        }

        return $data;
    }

    public function formatBatch(array $records): string
    {
        return match ($this->batchMode) {
            BatchMode::Json     => $this->formatBatchJson($records),
            BatchMode::Newlines => $this->formatBatchNewlines($records),
        };
    }

    /**
     * The keys in Monolog's order, the bags one level below the record.
     *
     * @return array<string, mixed>
     */
    private function normalizeRecord(LogRecord $record): array
    {
        return array_filter([
            'message'    => $record->message,
            'context'    => $this->normalizeBag($record->context),
            'level'      => $record->level->value,
            'level_name' => $record->level->getName(),
            'channel'    => $record->channel,
            'datetime'   => $this->formatRecordDate($record->datetime),
            'extra'      => $this->normalizeBag($record->extra),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private function normalizeBag(array $values): mixed
    {
        if ($values !== []) {
            return $this->normalize($values, 1);
        }

        // An empty bag is an object on the wire, as it is when populated.
        return $this->ignoreEmptyContextAndExtra ? null : new \stdClass();
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
