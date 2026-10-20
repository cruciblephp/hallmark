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

use function count;
use function explode;
use function get_object_vars;
use function get_resource_type;
use function gettype;
use function is_array;
use function is_bool;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function is_object;
use function is_resource;
use function is_scalar;
use function is_string;
use function json_encode;
use function json_last_error_msg;

use const JSON_PARTIAL_OUTPUT_ON_ERROR;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use Hallmark\Exception\RuntimeException;

/**
 * Base for formatters that need to flatten arbitrary context/extra values into
 * something safe to serialize.
 *
 * `normalize()` walks the value recursively — bounded by depth and item count so
 * a cyclic or enormous structure can never wedge the logger — and renders dates,
 * exceptions, resources and objects into predictable scalars/arrays.
 */
abstract class NormalizerFormatter implements FormatterInterface
{
    /** Dates found in context and extra, unless a format is given. */
    public const SIMPLE_DATE = 'Y-m-d\TH:i:sP';

    /** The record's own timestamp, unless a format is given. */
    public const RECORD_DATE = 'Y-m-d\TH:i:s.uP';

    protected string $dateFormat;
    protected string $recordDateFormat;
    protected bool $includeStacktraces = false;

    public function __construct(
        ?string $dateFormat = null,
        protected int $maxNormalizeDepth = 9,
        protected int $maxNormalizeItemCount = 1000,
    ) {
        $this->dateFormat       = $dateFormat ?? self::SIMPLE_DATE;
        $this->recordDateFormat = $dateFormat ?? self::RECORD_DATE;
    }

    public function formatBatch(array $records): string
    {
        $message = '';

        foreach ($records as $record) {
            $message .= $this->format($record);
        }

        return $message;
    }

    /** Toggle whether normalized exceptions carry their stack trace. */
    public function includeStacktraces(bool $include = true): static
    {
        $this->includeStacktraces = $include;

        return $this;
    }

    protected function formatRecordDate(\DateTimeInterface $datetime): string
    {
        return $datetime->format($this->recordDateFormat);
    }

    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        if ($depth > $this->maxNormalizeDepth) {
            return 'Over ' . $this->maxNormalizeDepth . ' levels deep, aborting normalization';
        }

        if ($data === null || is_scalar($data)) {
            if (is_float($data)) {
                if (is_nan($data)) {
                    return 'NaN';
                }
                if (is_infinite($data)) {
                    return $data > 0 ? 'INF' : '-INF';
                }
            }

            return $data;
        }

        if (is_array($data)) {
            // Fast path: a plain array of int/string/bool/null values — by far the
            // most common context shape — is already in final form, so return it
            // as-is instead of allocating a copy and recursing per element. Floats
            // are excluded (NaN/Inf need special handling), and the item-count cap
            // is preserved so an enormous array still takes the guarded slow path.
            if (count($data) <= $this->maxNormalizeItemCount && $this->isFlatScalarArray($data)) {
                return $data;
            }

            $normalized = [];
            $count      = 0;

            foreach ($data as $key => $value) {
                if ($count++ >= $this->maxNormalizeItemCount) {
                    $normalized['...'] = 'Over ' . $this->maxNormalizeItemCount . ' items, aborting normalization';

                    break;
                }

                $normalized[$key] = $this->normalize($value, $depth + 1);
            }

            return $normalized;
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format($this->dateFormat);
        }

        if ($data instanceof \Throwable) {
            return $this->normalizeException($data, $depth);
        }

        if ($data instanceof \JsonSerializable) {
            return $this->normalize($data->jsonSerialize(), $depth + 1);
        }

        if ($data instanceof \Stringable) {
            return $data->__toString();
        }

        if (is_object($data)) {
            return [$data::class => $this->normalize(get_object_vars($data), $depth + 1)];
        }

        if (is_resource($data)) {
            return '[resource(' . get_resource_type($data) . ')]';
        }

        return '[unknown(' . gettype($data) . ')]';
    }

    /**
     * Whether every value is a plain scalar that survives normalization
     * untouched — int, string, bool or null (floats excluded: NaN/Inf need care).
     *
     * @param array<array-key, mixed> $data
     */
    private function isFlatScalarArray(array $data): bool
    {
        foreach ($data as $value) {
            if ($value !== null && !is_int($value) && !is_string($value) && !is_bool($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizeException(\Throwable $e, int $depth): array
    {
        $data = [
            'class'   => $e::class,
            'message' => $e->getMessage(),
            'code'    => (int) $e->getCode(),
            'file'    => $e->getFile() . ':' . $e->getLine(),
        ];

        if ($this->includeStacktraces) {
            $data['trace'] = explode("\n", $e->getTraceAsString());
        }

        $previous = $e->getPrevious();

        if ($previous instanceof \Throwable) {
            $data['previous'] = $this->normalizeException($previous, $depth + 1);
        }

        return $data;
    }

    /**
     * Encode already-normalized data to JSON. `JSON_PARTIAL_OUTPUT_ON_ERROR`
     * keeps a single bad byte from turning one log line into a hard failure.
     */
    protected function toJson(mixed $data, bool $ignoreErrors = false): string
    {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION;

        if ($ignoreErrors) {
            $flags |= JSON_PARTIAL_OUTPUT_ON_ERROR;
        }

        $json = json_encode($data, $flags);

        if ($json === false) {
            throw new RuntimeException('Failed to encode log record to JSON: ' . json_last_error_msg());
        }

        return $json;
    }
}
