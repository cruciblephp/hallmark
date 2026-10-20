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

use function is_array;
use function is_string;
use function strtolower;

use Hallmark\LogRecord;

/**
 * Masks sensitive values in the context by key name, so secrets never reach a
 * log sink — the fast key-redaction Pino popularised.
 *
 * Matching is case-insensitive and recurses into nested arrays, so a `password`
 * three levels deep is caught too. Push this so it runs before any interpolation
 * processor, otherwise a secret already spliced into the message escapes it.
 */
final class RedactionProcessor implements ProcessorInterface
{
    /**
     * A conservative default aimed at auth and payment data — the fields that
     * most often leak into a `$context` array on a payment or login path.
     *
     * @var list<string>
     */
    public const array DEFAULT_KEYS = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
        'authorization', 'auth', 'access_token', 'refresh_token', 'client_secret',
        'private_key', 'card', 'card_number', 'cvv', 'cvc', 'pan', 'ssn',
    ];

    /** @var array<string, true> lower-cased key lookup */
    private array $redactKeys = [];

    /**
     * @param list<string> $keys context keys to mask (case-insensitive)
     */
    public function __construct(
        array $keys = self::DEFAULT_KEYS,
        private readonly string $replacement = '[REDACTED]',
        private readonly bool $recursive = true,
    ) {
        foreach ($keys as $key) {
            $this->redactKeys[strtolower($key)] = true;
        }
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($record->context === [] || $this->redactKeys === []) {
            return $record;
        }

        $redacted = $this->redact($record->context);

        if ($redacted === $record->context) {
            return $record;
        }

        return $record->with(context: $redacted);
    }

    /**
     * @param  array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && isset($this->redactKeys[strtolower($key)])) {
                $data[$key] = $this->replacement;

                continue;
            }

            if ($this->recursive && is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
