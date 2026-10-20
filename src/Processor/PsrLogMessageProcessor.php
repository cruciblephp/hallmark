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

use function array_key_exists;
use function array_keys;
use function count;
use function gettype;
use function is_array;
use function is_object;
use function is_scalar;
use function json_encode;
use function method_exists;
use function preg_match_all;
use function str_contains;
use function strtr;

use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

use Hallmark\LogRecord;

/**
 * Interpolates PSR-3 `{placeholder}` tokens in the message with matching values
 * from the context, exactly as the PSR-3 spec describes.
 *
 * With `$removeUsedContextFields` the consumed keys are dropped from the
 * context, so a value never appears twice — once inline, once in the trailing
 * context blob.
 *
 * A template's placeholder names are parsed once and cached (the .NET
 * `LoggerMessage` trick): re-logging the same template skips the regex scan, and
 * interpolation then costs O(placeholders) instead of O(context size).
 */
final class PsrLogMessageProcessor implements ProcessorInterface
{
    /**
     * Templates in a codebase are a small, fixed set, so this cache converges.
     * The cap is a guard against a caller that funnels unique strings through as
     * templates; on overflow we drop the cache rather than grow without bound.
     */
    private const int CACHE_LIMIT = 1000;

    /** How a date in the context is written when no `$dateFormat` is given. */
    public const string DEFAULT_DATE = 'Y-m-d\TH:i:s.uP';

    /** @var array<string, list<string>> message template => placeholder names */
    private array $placeholderCache = [];

    public function __construct(
        private readonly ?string $dateFormat = null,
        private readonly bool $removeUsedContextFields = false,
    ) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $placeholders = $this->placeholdersOf($record->message);

        if ($placeholders === []) {
            return $record;
        }

        $replacements = [];
        $context      = $record->context;

        foreach ($placeholders as $key) {
            if (!array_key_exists($key, $context)) {
                continue;
            }

            $replacements['{' . $key . '}'] = $this->stringify($context[$key]);

            if ($this->removeUsedContextFields) {
                unset($context[$key]);
            }
        }

        if ($replacements === []) {
            return $record;
        }

        return $record->with(
            message: strtr($record->message, $replacements),
            context: $context,
        );
    }

    /**
     * The distinct placeholder names in a template, parsed once and memoized.
     *
     * @return list<string>
     */
    private function placeholdersOf(string $message): array
    {
        if (!str_contains($message, '{')) {
            return [];
        }

        if (array_key_exists($message, $this->placeholderCache)) {
            return $this->placeholderCache[$message];
        }

        $names = [];

        if (preg_match_all('/\{([A-Za-z0-9_.]+)\}/', $message, $matches) > 0) {
            // Deduplicate while preserving first-seen order.
            foreach ($matches[1] as $name) {
                $names[$name] = true;
            }
            $names = array_keys($names);
        }

        if (count($this->placeholderCache) >= self::CACHE_LIMIT) {
            $this->placeholderCache = [];
        }

        return $this->placeholderCache[$message] = $names;
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format($this->dateFormat ?? self::DEFAULT_DATE);
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        if (is_object($value)) {
            return '[object ' . $value::class . ']';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[array]';
        }

        return '[' . gettype($value) . ']';
    }
}
