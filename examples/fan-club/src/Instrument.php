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

/** What a member plays, as in Laravents' fan club. */
enum Instrument: string
{
    case Guitar = 'guitar';
    case Bass   = 'bass';
    case Drums  = 'drums';
    case Keys   = 'keys';
}
