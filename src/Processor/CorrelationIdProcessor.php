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

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\LogRecord;
use Hallmark\Resettable;

/**
 * Stamps a correlation id onto every record so all log lines from one request or
 * job can be tied together after the fact — the piece needed to trace a single
 * flow across many log entries.
 *
 * Unlike {@see UidProcessor}, the id is adoptable and resettable: call
 * {@see self::adopt()} at the edge to inherit an incoming `X-Request-Id` /
 * `traceparent`, and {@see self::reset()} at a request boundary on a long-lived
 * worker so the next request starts a fresh trace. A record that already
 * carries the key, in its context or extra, is left as it is: one id per line.
 */
final class CorrelationIdProcessor implements ProcessorInterface, Resettable
{
    /** @var int<1, 64> */
    private readonly int $length;

    private string $id;

    public function __construct(
        private readonly string $key = 'correlation_id',
        ?string $id = null,
        int $length = 16,
    ) {
        if ($length < 1 || $length > 64) {
            throw new InvalidArgumentException('The correlation id length must be between 1 and 64.');
        }

        $this->length = $length;
        $this->id     = $id ?? RandomHex::generate($length);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if (array_key_exists($this->key, $record->context) || array_key_exists($this->key, $record->extra)) {
            return $record;
        }

        return $record->with(extra: [...$record->extra, $this->key => $this->id]);
    }

    public function getId(): string
    {
        return $this->id;
    }

    /** Inherit an id from upstream (e.g. an incoming request header). */
    public function adopt(string $id): void
    {
        $this->id = $id;
    }

    /** Start a fresh trace — typically between requests on a persistent worker. */
    public function reset(?string $id = null): void
    {
        $this->id = $id ?? RandomHex::generate($this->length);
    }
}
