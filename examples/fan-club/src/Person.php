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

namespace FanClub;

/** Someone on the site: a member of the band, with an instrument, or a fan. */
final readonly class Person
{
    public function __construct(
        public string $name,
        public ?Instrument $instrument = null,
    ) {}

    public function isMember(): bool
    {
        return $this->instrument instanceof Instrument;
    }
}
