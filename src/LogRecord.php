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

namespace Hallmark;

/**
 * An immutable snapshot of one logging event.
 *
 * Every field a processor or handler reads is `readonly`, so a record can be
 * fanned out to multiple handlers without one of them mutating what the next
 * sees. Enrichment goes through {@see self::with()}, which returns a fresh copy.
 *
 * The single exception is {@see self::$formatted}: a handler assigns its own
 * rendered string there immediately before writing. Because handlers run
 * sequentially (format-then-write, never interleaved) sharing that one mutable
 * slot is safe and spares an allocation per handler.
 */
final class LogRecord
{
    /**
     * @param array<array-key, mixed> $context caller-supplied data for this event
     * @param array<array-key, mixed> $extra   data added by processors
     */
    public function __construct(
        public readonly \DateTimeImmutable $datetime,
        public readonly string $channel,
        public readonly Level $level,
        public readonly string $message,
        public readonly array $context = [],
        public readonly array $extra = [],
        public string $formatted = '',
    ) {}

    /**
     * A copy with selected fields replaced. Processors use this to enrich a
     * record — typically the `extra` array — without touching shared state.
     *
     * @param array<array-key, mixed>|null $context
     * @param array<array-key, mixed>|null $extra
     */
    public function with(
        ?\DateTimeImmutable $datetime = null,
        ?string $channel = null,
        ?Level $level = null,
        ?string $message = null,
        ?array $context = null,
        ?array $extra = null,
        ?string $formatted = null,
    ): self {
        return new self(
            datetime:  $datetime  ?? $this->datetime,
            channel:   $channel   ?? $this->channel,
            level:     $level     ?? $this->level,
            message:   $message   ?? $this->message,
            context:   $context   ?? $this->context,
            extra:     $extra     ?? $this->extra,
            formatted: $formatted ?? $this->formatted,
        );
    }

    /**
     * The record as a plain array, for a consumer that wants a structure instead
     * of the object. Formatters build their own output and do not use it.
     *
     * @return array{
     *     message: string,
     *     context: array<array-key, mixed>,
     *     level: int,
     *     level_name: string,
     *     channel: string,
     *     datetime: string,
     *     extra: array<array-key, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'message'    => $this->message,
            'context'    => $this->context,
            'level'      => $this->level->value,
            'level_name' => $this->level->getName(),
            'channel'    => $this->channel,
            'datetime'   => $this->datetime->format(\DateTimeInterface::RFC3339_EXTENDED),
            'extra'      => $this->extra,
        ];
    }
}
