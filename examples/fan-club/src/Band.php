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

use Hallmark\Logger;
use LogicException;

/** Gives nicknames and changes the lineup; a fan may ask for a nickname, only a member gives one. */
final readonly class Band
{
    public function __construct(private Logger $log) {}

    public function giveNickname(Person $by, Person $to, string $nickname): bool
    {
        $context = ['by' => $by->name, 'to' => $to->name, 'nickname' => $nickname];

        if (!$by->isMember()) {
            $this->log->event(BandEvent::NicknameRefused, $context);

            return false;
        }

        $this->log->event(BandEvent::NicknameGiven, $context);

        return true;
    }

    /** $in takes $out's instrument; the returned Person is $in, now a member. */
    public function replace(Person $out, Person $in): Person
    {
        $instrument = $out->instrument ?? throw new LogicException($out->name . ' is not in the band.');

        $this->log->event(BandEvent::MemberReplaced, ['out' => $out->name, 'in' => $in->name, 'instrument' => $instrument->value]);

        return new Person($in->name, $instrument);
    }
}
