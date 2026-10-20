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

namespace Modules\FanClub\Handlers;

use function is_object;

use Illuminate\Support\Facades\Log;
use Laravents\Attributes\EventHandler;

use Laravents\Attributes\Header;

final class ArchiveBandEvents
{
    /**
     * Every Band event, one log line each, as Laravents' fan club archives them.
     *
     * @param object|array<string, mixed> $event
     */
    #[EventHandler('band.*')]
    public function __invoke(object|array $event, #[Header('message_id')] string $messageId): void
    {
        Log::info('Band event', ['event' => is_object($event) ? $event::class : $event, 'message_id' => $messageId]);
    }
}
