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

namespace Hallmark\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Log\LogManager;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the `hallmark` log driver. Discovered by Laravel's package
 * discovery; a channel then needs only `'driver' => 'hallmark'`.
 */
final class HallmarkServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // The log manager binds a driver callback to itself, so it cannot be static.
        $this->app->make(LogManager::class)->extend('hallmark', fn(Application $app, array $config): ChannelLogger => (new CreateHallmarkLogger($app))($config));
    }
}
