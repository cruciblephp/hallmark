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
 *
 * With `$perRecord`, each record gets a uid of its own instead: an id for the
 * line itself, which a reader cites and de-duplicates by.
 *
 * With an {@see IdGenerator}, the ids are the application's kind instead of
 * random hex, and `$length` does not apply: in Laravel, binding the interface
 * in the container is enough for a channel's `processors` to receive it.
 */
final class UidProcessor implements ProcessorInterface, Resettable
{
    /** @var int<1, 32> */
    private readonly int $length;

    private string $uid;

    public function __construct(int $length = 7, private readonly bool $perRecord = false, private readonly ?IdGenerator $generator = null)
    {
        if ($length < 1 || $length > 32) {
            throw new InvalidArgumentException('The uid length must be between 1 and 32.');
        }

        $this->length = $length;
        $this->uid    = $this->newUid();
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($this->perRecord) {
            $this->uid = $this->newUid();
        }

        return $record->with(extra: [...$record->extra, 'uid' => $this->uid]);
    }

    public function getUid(): string
    {
        return $this->uid;
    }

    public function reset(): void
    {
        $this->uid = $this->newUid();
    }

    private function newUid(): string
    {
        if (!$this->generator instanceof IdGenerator) {
            return RandomHex::generate($this->length);
        }

        $uid = $this->generator->generate();

        return $uid !== '' ? $uid : throw new InvalidArgumentException('An IdGenerator returned an empty id.');
    }
}
