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

namespace Modules\Band\Handlers;

use Illuminate\Support\Facades\Log;
use Laravents\Attributes\CommandHandler;
use Modules\Band\Api\GiveNickname;
use Modules\Band\Api\NicknameGiven;

final class BandHandlers
{
    #[CommandHandler]
    public function give(GiveNickname $command): void
    {
        Log::info('Nickname given', ['member_id' => $command->memberId, 'nickname' => $command->nickname]);

        NicknameGiven::publish(memberId: $command->memberId, nickname: $command->nickname);
    }
}
