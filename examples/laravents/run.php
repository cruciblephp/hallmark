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

// A Laravents command on a Hallmark channel: php examples/laravents/run.php [log directory]

use function Orchestra\Testbench\default_skeleton_path;

use Hallmark\Laravel\HallmarkServiceProvider;
use Laravents\Discovery\Registry;
use Laravents\Discovery\RegistryBuilder;
use Laravents\Facades\Laravents;
use Laravents\LaraventsServiceProvider;
use Modules\Band\Handlers\BandHandlers;
use Modules\FanClub\Handlers\ArchiveBandEvents;

use Orchestra\Testbench\Foundation\Application;

require __DIR__ . '/../../vendor/autoload.php';

$logDirectory = $argv[1] ?? __DIR__ . '/var/logs';

$app = Application::create(basePath: default_skeleton_path());
$app->register(LaraventsServiceProvider::class);
$app->register(HallmarkServiceProvider::class);

// The application's logging config: one hallmark channel, the default.
config([
    'logging.default'           => 'hallmark',
    'logging.channels.hallmark' => ['driver' => 'hallmark', 'path' => $logDirectory . '/laravel.log', 'name' => 'fan-club'],
]);

// The two modules' handlers, as discovery would find them under Modules\.
$app->instance(Registry::class, (new RegistryBuilder('Modules'))->build([BandHandlers::class, ArchiveBandEvents::class]));

// John gives George a nickname; the request's correlation id goes along.
Laravents::command('band.give_nickname', ['memberId' => 2, 'nickname' => 'The Quiet One'], ['correlation_id' => 'req-42']);

echo file_get_contents($logDirectory . '/laravel.log');
