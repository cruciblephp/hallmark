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

use Laravents\Concerns\Publishable;

/** Event band.nickname_given: a member has a new nickname. */
final readonly class NicknameGiven
{
    use Publishable;

    public function __construct(public int $memberId, public string $nickname) {}
}
