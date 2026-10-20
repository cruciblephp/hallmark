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

use Hallmark\Event\EventCode;
use Hallmark\Level;

/** What the Band module logs. */
enum BandEvent: string implements EventCode
{
    case NicknameGiven   = 'BAND-1001';
    case MemberReplaced  = 'BAND-1002';
    case NicknameRefused = 'BAND-1003';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return match ($this) {
            self::NicknameGiven   => Level::Info,
            self::MemberReplaced  => Level::Notice,
            self::NicknameRefused => Level::Warning,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::NicknameGiven   => '{by} calls {to} "{nickname}"',
            self::MemberReplaced  => '{out} leaves the band and {in} takes the {instrument}',
            self::NicknameRefused => 'Only a member gives a nickname: {by} asked to call {to} "{nickname}"',
        };
    }
}
