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

use function is_bool;
use function is_scalar;
use function str_replace;
use function strtr;
use function var_export;

use Hallmark\LogRecord;

/**
 * Renders a record onto a single line from a `%placeholder%` template — the
 * familiar `[time] channel.LEVEL: message {context} {extra}` layout.
 *
 * Newlines inside the message and serialized data are collapsed to spaces by
 * default so one event is always one line (grep-friendly); pass
 * `$allowInlineLineBreaks` to keep multi-line payloads such as stack traces.
 */
final class LineFormatter extends NormalizerFormatter
{
    public const string DEFAULT_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";

    private readonly string $format;

    public function __construct(
        ?string $format = null,
        ?string $dateFormat = null,
        private readonly bool $allowInlineLineBreaks = false,
        private readonly bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ) {
        $this->format = $format ?? self::DEFAULT_FORMAT;

        parent::__construct($dateFormat);
        $this->includeStacktraces($includeStacktraces);
    }

    public function format(LogRecord $record): string
    {
        $context = $this->normalize($record->context);
        $extra   = $this->normalize($record->extra);

        // strtr replaces in a single pass and prefers the longest matching key,
        // so %level_name% is never clobbered by %level%, and a value that itself
        // contains a %token% is not re-expanded.
        return strtr($this->format, [
            '%datetime%'   => $this->formatRecordDate($record->datetime),
            '%channel%'    => $record->channel,
            '%level%'      => (string) $record->level->value,
            '%level_name%' => $record->level->getName(),
            '%message%'    => $this->stringify($record->message),
            '%context%'    => $this->renderBag($context),
            '%extra%'      => $this->renderBag($extra),
        ]);
    }

    /** Render a normalized context/extra bag, honouring the empty-suppression flag. */
    private function renderBag(mixed $bag): string
    {
        if ($this->ignoreEmptyContextAndExtra && $bag === []) {
            return '';
        }

        return $this->stringify($bag);
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

    private function replaceNewlines(string $str): string
    {
        if ($this->allowInlineLineBreaks) {
            return $str;
        }

        return str_replace(["\r\n", "\r", "\n"], ' ', $str);
    }
}
