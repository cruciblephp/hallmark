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

/** What the Press module logs. */
enum PressEvent: string implements EventCode
{
    case HeadlinePrinted = 'PRESS-2001';
    case PrintFailed     = 'PRESS-2002';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return match ($this) {
            self::HeadlinePrinted => Level::Info,
            self::PrintFailed     => Level::Error,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::HeadlinePrinted => 'Headline {publication}: {member} is "{nickname}"',
            self::PrintFailed     => 'Headline {publication} was not printed',
        };
    }
}
