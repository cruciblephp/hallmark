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

use function array_slice;
use function count;
use function get_parent_class;
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
use function json_decode;
use function json_encode;
use function json_last_error_msg;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strpos;
use function substr;

use const DIRECTORY_SEPARATOR;
use const JSON_INVALID_UTF8_SUBSTITUTE;
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
    protected ?int $maxTraceLength     = null;
    protected string $basePath         = '';

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

    /** Keep at most this many trace frames; null keeps them all. */
    public function setMaxTraceLength(?int $maxTraceLength): static
    {
        $this->maxTraceLength = $maxTraceLength;

        return $this;
    }

    /** Strip this path from the start of every file name in an exception and its trace. */
    public function setBasePath(string $path = ''): static
    {
        $this->basePath = $path === '' ? '' : rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $this;
    }

    protected function formatRecordDate(\DateTimeInterface $datetime): string
    {
        return $datetime->format($this->recordDateFormat);
    }

    /**
     * As Monolog's: a record's bags are normalized at depth 1, the record itself
     * being depth 0.
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
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

        if ($depth > $this->maxNormalizeDepth) {
            return 'Over ' . $this->maxNormalizeDepth . ' levels deep, aborting normalization';
        }

        if (is_array($data)) {
            return $this->normalizeArray($data, $depth);
        }

        if ($data instanceof \DateTimeInterface) {
            return $data->format($this->dateFormat);
        }

        if ($data instanceof \Throwable) {
            return $this->normalizeException($data, $depth);
        }

        if (is_object($data)) {
            return [self::className($data::class) => $this->objectValue($data)];
        }

        if (is_resource($data)) {
            return '[resource(' . get_resource_type($data) . ')]';
        }

        return '[unknown(' . gettype($data) . ')]';
    }

    /**
     * @param  array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    protected function normalizeArray(array $data, int $depth): array
    {
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
                $normalized['...'] = 'Over ' . $this->maxNormalizeItemCount . ' items (' . count($data) . ' total), aborting normalization';

                break;
            }

            $normalized[$key] = $this->normalize($value, $depth + 1);
        }

        return $normalized;
    }

    /**
     * An object's value under its class name, left for the encoder as Monolog
     * leaves it: what `jsonSerialize()` returns, its string, or its public
     * properties through a JSON round trip.
     */
    private function objectValue(object $data): mixed
    {
        if ($data instanceof \JsonSerializable) {
            return $data->jsonSerialize();
        }

        if ($data instanceof \__PHP_Incomplete_Class) {
            $name = (new \ArrayObject($data))['__PHP_Incomplete_Class_Name'];

            return is_string($name) ? $name : '';
        }

        if ($data instanceof \Stringable) {
            try {
                return $data->__toString();
            } catch (\Throwable) {
                // A failing __toString() falls back to the properties.
            }
        }

        return json_decode($this->toJson($data, true), true);
    }

    /**
     * An anonymous class's name without the NUL byte and declaration site PHP
     * puts in it: `class@anonymous`, or its parent's name before `@anonymous`.
     */
    protected static function className(string $class): string
    {
        $pos = strpos($class, "@anonymous\0");

        if ($pos === false) {
            return $class;
        }

        $parent = get_parent_class($class);

        return $parent === false ? substr($class, 0, $pos + 10) : $parent . '@anonymous';
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
     * Each trace frame is `file:line`, as Monolog writes it: a frame's arguments
     * never reach a sink, whatever `zend.exception_ignore_args` says.
     *
     * @return array<array-key, mixed>|string
     */
    protected function normalizeException(\Throwable $e, int $depth): array|string
    {
        if ($depth > $this->maxNormalizeDepth) {
            return ['Over ' . $this->maxNormalizeDepth . ' levels deep, aborting normalization'];
        }

        if ($e instanceof \JsonSerializable) {
            return (array) $e->jsonSerialize();
        }

        $data = [
            'class'   => self::className($e::class),
            'message' => $e->getMessage(),
            'code'    => (int) $e->getCode(),
            'file'    => $this->stripBasePath($e->getFile()) . ':' . $e->getLine(),
        ];

        if ($e instanceof \SoapFault) {
            if (isset($e->faultcode)) {
                $data['faultcode'] = $e->faultcode;
            }

            if (isset($e->faultactor)) {
                $data['faultactor'] = $e->faultactor;
            }

            if ($e->detail !== null) {
                if (is_string($e->detail)) {
                    $data['detail'] = $e->detail;
                } elseif (is_object($e->detail) || is_array($e->detail)) {
                    $data['detail'] = $this->toJson($e->detail, true);
                }
            }
        }

        if ($this->includeStacktraces) {
            foreach (array_slice($e->getTrace(), 0, $this->maxTraceLength) as $frame) {
                $data['trace'][] = isset($frame['file'])
                    ? $this->stripBasePath($frame['file']) . ':' . ($frame['line'] ?? 0)
                    : 'internal[' . $this->internalCall($frame) . ']:0';
            }
        }

        $previous = $e->getPrevious();

        if ($previous instanceof \Throwable) {
            $data['previous'] = $this->normalizeException($previous, $depth + 1);
        }

        return $data;
    }

    protected function stripBasePath(string $file): string
    {
        return $this->basePath !== '' && str_starts_with($file, $this->basePath) ? substr($file, strlen($this->basePath)) : $file;
    }

    /**
     * A frame the engine called has no file: a shutdown function, a callback an
     * internal function ran, a destructor. It is written by name instead.
     *
     * @param array{function: string, class?: class-string, type?: string} $frame
     */
    private function internalCall(array $frame): string
    {
        $call = $frame['function'];

        // Since PHP 8.4 a closure's name already carries its class.
        if (isset($frame['class']) && !str_starts_with($call, '{closure:')) {
            $call = str_ends_with($call, '\\{closure}') ? '{closure}' : $call;
            $call = $frame['class'] . ($frame['type'] ?? '::') . $call;
        }

        // An anonymous class's name holds a NUL byte and its declaration site.
        $call = preg_replace('{@anonymous\x00.*?\$[0-9a-f]++(?=::|$)}s', '@anonymous', $call) ?? $call;

        if ($this->basePath !== '') {
            return preg_replace('{' . preg_quote($this->basePath) . '}', '', $call, 1) ?? $call;
        }

        return $call;
    }

    /**
     * Encode to JSON with Monolog's flags: an invalid UTF-8 byte becomes U+FFFD,
     * and a value JSON cannot hold, a NaN or a pure enum, becomes `0` or `null`
     * rather than failing the line.
     */
    protected function toJson(mixed $data, bool $ignoreErrors = false): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        if ($json === false) {
            if ($ignoreErrors) {
                return 'null';
            }

            throw new RuntimeException('Failed to encode log record to JSON: ' . json_last_error_msg());
        }

        return $json;
    }
}
