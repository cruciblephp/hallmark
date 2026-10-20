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

use Hallmark\Exception\InvalidArgumentException;
use Hallmark\LogRecord;
use Hallmark\Resettable;

/**
 * Stamps every record with the same random `extra.uid`, so all log lines from a
 * single request or process can be correlated after the fact. Call
 * {@see self::reset()} at a request boundary to start a fresh correlation id.
 */
final class UidProcessor implements ProcessorInterface, Resettable
{
    /** @var int<1, 32> */
    private readonly int $length;

    private string $uid;

    public function __construct(int $length = 7)
    {
        if ($length < 1 || $length > 32) {
            throw new InvalidArgumentException('The uid length must be between 1 and 32.');
        }

        $this->length = $length;
        $this->uid    = RandomHex::generate($length);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(extra: [...$record->extra, 'uid' => $this->uid]);
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function reset(): void
    {
        $this->uid = RandomHex::generate($this->length);
    }
}
