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

use function is_int;
use function is_string;

use Illuminate\Contracts\Foundation\Application;
use Hallmark\Handler\RotatingFileHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Processor\PsrLogMessageProcessor;

/**
 * Builds a `hallmark` log channel from Laravel's own channel keys: those of the
 * `single` driver (`path`, `level`, `bubble`, `permission`, `locking`,
 * `replace_placeholders`, `name`), and `days` from the `daily` driver for a
 * file per day. Usable as `'driver' => 'hallmark'` or, as Laravel's custom
 * channels are, `'driver' => 'custom', 'via' => CreateHallmarkLogger::class`.
 */
final readonly class CreateHallmarkLogger
{
    public function __construct(private Application $app) {}

    /**
     * @param array<array-key, mixed> $config
     */
    public function __invoke(array $config): ChannelLogger
    {
        $path       = $this->string($config, 'path', $this->app->storagePath('logs/laravel.log'));
        $level      = Level::fromPsr($this->string($config, 'level', 'debug'));
        $bubble     = ($config['bubble'] ?? true) === true;
        $permission = isset($config['permission']) && is_int($config['permission']) ? $config['permission'] : null;
        $locking    = ($config['locking'] ?? false) === true;

        $handler = isset($config['days']) && is_int($config['days'])
            ? new RotatingFileHandler($path, $config['days'], $level, $bubble, $permission, $locking)
            : new StreamHandler($path, $level, $bubble, $permission, $locking);

        $processors = [new ContextLogProcessor($this->app)];

        if (($config['replace_placeholders'] ?? false) === true) {
            $processors[] = new PsrLogMessageProcessor();
        }

        // Laravel names an unnamed channel after the environment, or 'production'.
        $environment = $this->app->environment();
        $name        = $this->string($config, 'name', is_string($environment) ? $environment : 'production');

        return new ChannelLogger(new Logger($name, [$handler], $processors));
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function string(array $config, string $key, string $default): string
    {
        return isset($config[$key]) && is_string($config[$key]) ? $config[$key] : $default;
    }
}
