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

/** Welcomes a new fan; the signup's email and password reach the log masked. */
final readonly class FanClub
{
    public function __construct(private Logger $log) {}

    public function welcome(Person $fan, string $email, string $password): void
    {
        $this->log->event(FanClubEvent::FanWelcomed, ['fan' => $fan->name, 'email' => $email, 'password' => $password]);
    }
}
