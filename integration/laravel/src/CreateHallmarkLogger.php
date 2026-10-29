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

use function get_debug_type;
use function is_a;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function preg_match;
use function sprintf;
use function var_export;

use Illuminate\Contracts\Foundation\Application;
use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Formatter\FormatterInterface;
use Hallmark\Handler\RotatingFileHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Processor\ProcessorInterface;
use Hallmark\Processor\PsrLogMessageProcessor;

/**
 * Builds a `hallmark` log channel from Laravel's own channel keys: those of the
 * `single` driver (`path`, `level`, `bubble`, `permission`, `locking`,
 * `replace_placeholders`, `name`), `days` from the `daily` driver for a
 * file per day, and `formatter`, `formatter_with` and `processors`, as the log
 * manager reads them for every channel. Usable as `'driver' => 'hallmark'` or, as Laravel's custom
 * channels are, `'driver' => 'custom', 'via' => CreateHallmarkLogger::class`.
 *
 * A value reads as `env()` gives it: `"14"` for a number, `"1"` or `"0"` for a
 * boolean. Any other value is refused, never dropped.
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
        $bubble     = $this->bool($config, 'bubble', true);
        $permission = $this->int($config, 'permission');
        $locking    = $this->bool($config, 'locking', false);
        $days       = $this->int($config, 'days');

        $handler = $days !== null
            ? new RotatingFileHandler($path, $days, $level, $bubble, $permission, $locking)
            : new StreamHandler($path, $level, $bubble, $permission, $locking);

        $formatter = $this->formatter($config);

        if ($formatter instanceof FormatterInterface) {
            $handler->setFormatter($formatter);
        }

        $processors = [new ContextLogProcessor($this->app)];

        if ($this->bool($config, 'replace_placeholders', false)) {
            $processors[] = new PsrLogMessageProcessor();
        }

        $processors = [...$processors, ...$this->processors($config)];

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
        $value = $config[$key] ?? null;

        return match (true) {
            $value === null    => $default,
            is_string($value)  => $value,
            default            => throw $this->refuse($key, $value, 'a string'),
        };
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function int(array $config, string $key): ?int
    {
        $value = $config[$key] ?? null;

        return match (true) {
            $value === null, is_int($value)                                => $value,
            is_string($value) && preg_match('/^[0-9]+$/D', $value) === 1   => (int) $value,
            default                                                        => throw $this->refuse($key, $value, 'an integer'),
        };
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function bool(array $config, string $key, bool $default): bool
    {
        $value = $config[$key] ?? null;

        return match (true) {
            $value === null                   => $default,
            is_bool($value)                   => $value,
            $value === 1, $value === '1'      => true,
            $value === 0, $value === '0'      => false,
            default                           => throw $this->refuse($key, $value, 'a boolean'),
        };
    }

    /**
     * `formatter` and `formatter_with`, as Laravel's log manager reads them: a
     * formatter class built by the container with those arguments, and `default`
     * or no key for the handler's own.
     *
     * @param array<array-key, mixed> $config
     */
    private function formatter(array $config): ?FormatterInterface
    {
        $class = $config['formatter'] ?? null;

        if ($class === null || $class === 'default') {
            return null;
        }

        if (!is_string($class) || !is_a($class, FormatterInterface::class, true)) {
            throw $this->refuse('formatter', $class, 'a class implementing ' . FormatterInterface::class);
        }

        return $this->app->make($class, $this->with($config, 'formatter_with'));
    }

    /**
     * `processors`, as Laravel's log manager reads them: each a processor class,
     * or `['processor' => class, 'with' => arguments]`, built by the container
     * and run after the channel's own.
     *
     * @param  array<array-key, mixed> $config
     * @return list<ProcessorInterface>
     */
    private function processors(array $config): array
    {
        $entries = $config['processors'] ?? [];

        if (!is_array($entries)) {
            throw $this->refuse('processors', $entries, 'a list of processors');
        }

        $processors = [];

        foreach ($entries as $entry) {
            $class = is_array($entry) ? $entry['processor'] ?? null : $entry;

            if (!is_string($class) || !is_a($class, ProcessorInterface::class, true)) {
                throw $this->refuse('processors', $entry, 'a list of classes implementing ' . ProcessorInterface::class);
            }

            $processors[] = $this->app->make($class, is_array($entry) ? $this->with($entry, 'with') : []);
        }

        return $processors;
    }

    /**
     * Constructor arguments by name, for the container.
     *
     * @param  array<array-key, mixed> $config
     * @return array<string, mixed>
     */
    private function with(array $config, string $key): array
    {
        $value = $config[$key] ?? [];

        if (!is_array($value)) {
            throw $this->refuse($key, $value, 'an array of constructor arguments');
        }

        $with = [];

        foreach ($value as $name => $argument) {
            $with[is_string($name) ? $name : throw $this->refuse($key, $value, 'an array of constructor arguments by name')] = $argument;
        }

        return $with;
    }

    private function refuse(string $key, mixed $value, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('The hallmark channel\'s "%s" must be %s, got %s %s.', $key, $expected, get_debug_type($value), var_export($value, true)));
    }
}
