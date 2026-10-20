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

/** What the FanClub module logs. */
enum FanClubEvent: string implements EventCode
{
    case FanWelcomed = 'FAN-3001';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return Level::Info;
    }

    public function message(): string
    {
        return 'Welcome to the fan club, {fan}';
    }
}
