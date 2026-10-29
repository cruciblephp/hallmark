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
use function array_key_exists;
use function array_slice;
use function implode;
use function is_array;
use function is_object;
use function is_scalar;
use function is_string;
use function ord;
use function sprintf;
use function strlen;
use function substr;

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Exception\RuntimeException;
use Hallmark\LogRecord;

/**
 * Serializes each record as a single JSON object — the natural feed for
 * structured log stores (ELK, Loki, CloudWatch) and anything that ingests
 * newline-delimited JSON.
 *
 * `$maxLineBytes` caps a record's JSON, its newline included: a record that
 * would pass it has its longest strings cut, on a UTF-8 boundary, until it
 * fits, and says so with `extra.truncated`. A writer that appends one line in
 * one write can then rely on the size it keeps whole.
 */
final class JsonFormatter extends NormalizerFormatter
{
    public function __construct(
        private readonly BatchMode $batchMode = BatchMode::Json,
        private readonly bool $appendNewline = true,
        ?string $dateFormat = null,
        private readonly bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
        private readonly ?int $maxLineBytes = null,
    ) {
        if ($maxLineBytes !== null && $maxLineBytes < 1) {
            throw new InvalidArgumentException(sprintf('The line cap must be a positive number of bytes, got %d.', $maxLineBytes));
        }

        parent::__construct($dateFormat);
        $this->includeStacktraces($includeStacktraces);
    }

    public function format(LogRecord $record): string
    {
        return $this->toJson($this->fitRecord($record), true) . ($this->appendNewline ? "\n" : '');
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
     * The record as it is written: within the line cap, its newline counted, when
     * one is set. The longest string in the message, the context or the extra is
     * cut until the JSON fits, so the facts that are short survive whole; when
     * none is left to cut, the bags go. A record whose channel and date alone
     * pass the cap cannot be written within it, and is refused.
     *
     * @return array<string, mixed>
     */
    private function fitRecord(LogRecord $record): array
    {
        $normalized = $this->normalizeRecord($record);

        if ($this->maxLineBytes === null) {
            return $normalized;
        }

        $budget = $this->maxLineBytes - 1;

        if (strlen($this->toJson($normalized, true)) <= $budget) {
            return $normalized;
        }

        $extra               = $normalized['extra'] ?? [];
        $normalized['extra'] = [...(is_array($extra) ? $extra : []), 'truncated' => true];

        while (($over = strlen($this->toJson($normalized, true)) - $budget) > 0) {
            if (!$this->cutLongest($normalized, $over)) {
                $normalized['message'] = '';
                $normalized['context'] = new \stdClass();
                $normalized['extra']   = ['truncated' => true];

                if (strlen($this->toJson($normalized, true)) > $budget) {
                    throw new RuntimeException(sprintf('A record of channel "%s" cannot be written within %d bytes.', $record->channel, $this->maxLineBytes));
                }

                break;
            }
        }

        return $normalized;
    }

    /**
     * Cuts the longest non-empty string under the message, the context or the
     * extra by `$over` bytes, or to nothing when it is shorter. False when no
     * such string is left.
     *
     * @param array<string, mixed> $normalized
     */
    private function cutLongest(array &$normalized, int $over): bool
    {
        $top    = null;
        $path   = [];
        $length = 0;

        foreach (['message', 'context', 'extra'] as $key) {
            if (isset($normalized[$key]) && self::findLongest($normalized[$key], [], $path, $length)) {
                $top = $key;
            }
        }

        if ($top === null) {
            return false;
        }

        $normalized[$top] = self::cutAt($normalized[$top], $path, $length > $over ? $length - $over : 0);

        return true;
    }

    /**
     * Whether `$value` holds a non-empty string longer than `$length`; if so, the
     * longest one's place below `$value` goes to `$path` and its length to `$length`.
     *
     * @param list<array-key> $at
     * @param list<array-key> $path
     */
    private static function findLongest(mixed $value, array $at, array &$path, int &$length): bool
    {
        if (is_string($value)) {
            if (strlen($value) <= $length) {
                return false;
            }

            $length = strlen($value);
            $path   = $at;

            return true;
        }

        $found = false;

        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $found = self::findLongest($item, [...$at, $key], $path, $length) || $found;
            }
        }

        return $found;
    }

    /**
     * `$value` with the string at `$path` cut to its first `$keep` bytes.
     *
     * @param list<array-key> $path
     */
    private static function cutAt(mixed $value, array $path, int $keep): mixed
    {
        if ($path === []) {
            return is_string($value) ? self::utf8Prefix($value, $keep) : $value;
        }

        $key = $path[0];

        if (!is_array($value) || !array_key_exists($key, $value)) {
            return $value;
        }

        $value[$key] = self::cutAt($value[$key], array_slice($path, 1), $keep);

        return $value;
    }

    /** The first `$bytes` bytes of `$text`, shortened so no UTF-8 character is split. */
    private static function utf8Prefix(string $text, int $bytes): string
    {
        // A continuation byte (10xxxxxx) at the cut belongs to the character before it.
        while ($bytes > 0 && $bytes < strlen($text) && (ord($text[$bytes]) & 0xC0) === 0x80) {
            $bytes--;
        }

        return substr($text, 0, $bytes);
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
            $normalized[] = $this->fitRecord($record);
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
            $lines[] = $this->toJson($this->fitRecord($record), true);
        }

        return implode("\n", $lines);
    }
}
