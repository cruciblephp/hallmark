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

namespace Modules\Band\Api;

use Laravents\Concerns\Sendable;

/**
 * Command band.give_nickname: a member gives another member a nickname, as in
 * Laravents' fan club.
 */
final readonly class GiveNickname
{
    use Sendable;

    public function __construct(public int $memberId, public string $nickname) {}
}
