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

use function array_key_exists;
use function array_slice;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_object;
use function is_scalar;
use function is_string;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function strtr;
use function var_export;

use const DIRECTORY_SEPARATOR;
use const PREG_SET_ORDER;

use Hallmark\LogRecord;

/**
 * Renders a record onto a single line from a `%placeholder%` template — the
 * familiar `[time] channel.LEVEL: message {context} {extra}` layout.
 *
 * Newlines inside the message and serialized data are collapsed to spaces by
 * default so one event is always one line (grep-friendly); pass
 * `$allowInlineLineBreaks` to keep multi-line payloads such as stack traces.
 * `%context.key%` and `%extra.key%` write one value, which then leaves its bag.
 */
final class LineFormatter extends NormalizerFormatter
{
    public const string DEFAULT_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    private readonly string $format;

    public function __construct(
        ?string $format = null,
        ?string $dateFormat = null,
        private bool $allowInlineLineBreaks = false,
        private readonly bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ) {
        $this->format = $format ?? self::DEFAULT_FORMAT;

        parent::__construct($dateFormat);
        $this->includeStacktraces($includeStacktraces);
    }

    /** A trace spans lines, so turning it on keeps line breaks, as in Monolog. */
    public function includeStacktraces(bool $include = true): static
    {
        parent::includeStacktraces($include);

        if ($include) {
            $this->allowInlineLineBreaks = true;
        }

        return $this;
    }

    public function format(LogRecord $record): string
    {
        $context = $this->normalize($record->context, 1);
        $extra   = $this->normalize($record->extra, 1);
        $context = is_array($context) ? $context : [];
        $extra   = is_array($extra) ? $extra : [];

        // Resolved on the template, in one pass, so text inside a value is never expanded.
        $replace = [];

        preg_match_all('/%(extra|context)\.(.+?)%/', $this->format, $matches, PREG_SET_ORDER);

        foreach ($matches as [$token, $bag, $key]) {
            if ($bag === 'extra' && array_key_exists($key, $extra)) {
                $replace[$token] = $this->stringify($extra[$key]);
                unset($extra[$key]);
            } elseif ($bag === 'context' && array_key_exists($key, $context)) {
                $replace[$token] = $this->stringify($context[$key]);
                unset($context[$key]);
            } else {
                $replace[$token] = '';
            }
        }

        return strtr($this->format, $replace + [
            '%message%'    => $this->stringify($record->message),
            '%context%'    => $this->ignoreEmptyContextAndExtra && count($context) === 0 ? '' : $this->stringify($context),
            '%level%'      => (string) $record->level->value,
            '%level_name%' => $record->level->getName(),
            '%channel%'    => $this->stringify($record->channel),
            '%datetime%'   => $this->stringify($this->formatRecordDate($record->datetime)),
            '%extra%'      => $this->ignoreEmptyContextAndExtra && count($extra) === 0 ? '' : $this->stringify($extra),
        ]);
    }

    /**
     * An exception and each previous one as Monolog writes them on a line:
     * `[object] (Class(code: 3): message at file:line)`.
     */
    protected function normalizeException(\Throwable $e, int $depth): string
    {
        $str = $this->formatException($e);

        for ($previous = $e->getPrevious(); $previous instanceof \Throwable; $previous = $previous->getPrevious()) {
            if (++$depth > $this->maxNormalizeDepth) {
                $str .= "\n[previous exception] Over " . $this->maxNormalizeDepth . ' levels deep, aborting normalization';

                break;
            }

            $str .= "\n[previous exception] " . $this->formatException($previous);
        }

        return $str;
    }

    private function formatException(\Throwable $e): string
    {
        $str = '[object] (' . self::className($e::class) . '(code: ' . $e->getCode();

        if ($e instanceof \SoapFault) {
            if (isset($e->faultcode)) {
                $str .= ' faultcode: ' . $e->faultcode;
            }

            if (isset($e->faultactor)) {
                $str .= ' faultactor: ' . $e->faultactor;
            }

            if ($e->detail !== null) {
                if (is_string($e->detail)) {
                    $str .= ' detail: ' . $e->detail;
                } elseif (is_object($e->detail) || is_array($e->detail)) {
                    $str .= ' detail: ' . $this->toJson($e->detail, true);
                }
            }
        }

        $str .= '): ' . $e->getMessage() . ' at ' . strtr($this->stripBasePath($e->getFile()), DIRECTORY_SEPARATOR, '/') . ':' . $e->getLine() . ')';

        return $this->includeStacktraces ? $str . $this->stacktrace($e) : $str;
    }

    /**
     * The trace in `getTraceAsString()`'s layout, as PHP's production ini writes
     * it whatever the ini: a call without its arguments, and the file an include
     * or require loaded as `'...'`. That is what Monolog writes there.
     */
    private function stacktrace(\Throwable $e): string
    {
        $frames = $e->getTrace();
        $lines  = [];

        foreach (array_slice($frames, 0, $this->maxTraceLength) as $i => $frame) {
            $where   = isset($frame['file']) ? $this->stripBasePath($frame['file']) . '(' . ($frame['line'] ?? 0) . ')' : '[internal function]';
            $args    = in_array($frame['function'], ['include', 'include_once', 'require', 'require_once'], true) ? "'...'" : '';
            $lines[] = '#' . $i . ' ' . $where . ': ' . ($frame['class'] ?? '') . ($frame['type'] ?? '') . $frame['function'] . '(' . $args . ')';
        }

        $lines[] = '#' . count($lines) . ' {main}';

        return "\n[stacktrace]\n" . strtr(implode("\n", $lines), DIRECTORY_SEPARATOR, '/') . "\n";
    }

    private function stringify(mixed $value): string
    {
        return $this->replaceNewlines($this->convertToString($value));
    }

    private function convertToString(mixed $data): string
    {
        if ($data === null || is_bool($data)) {
            return var_export($data, true);
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        return $this->toJson($data, true);
    }

    /** With line breaks kept, a `\n` escaped inside JSON is written as a break too. */
    private function replaceNewlines(string $str): string
    {
        if (!$this->allowInlineLineBreaks) {
            return str_replace(["\r\n", "\r", "\n"], ' ', $str);
        }

        if (str_starts_with($str, '{') || str_starts_with($str, '[')) {
            return preg_replace('/(?<!\\\\)\\\\[rn]/', "\n", $str) ?? $str;
        }

        return $str;
    }
}
