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

use Illuminate\Contracts\Container\Container;
use Illuminate\Log\Context\Repository;
use Hallmark\LogRecord;
use Hallmark\Processor\ProcessorInterface;

/**
 * Adds Laravel's Context to every record's `extra`, as Laravel's own
 * `ContextLogProcessor` does for a Monolog channel — so ids put in Context
 * (a request id, Laravents' `correlation_id`) appear on every line.
 */
final readonly class ContextLogProcessor implements ProcessorInterface
{
    public function __construct(private Container $container) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        if (!$this->container->bound(Repository::class)) {
            return $record;
        }

        $context = $this->container->make(Repository::class)->all();

        return $context === [] ? $record : $record->with(extra: [...$record->extra, ...$context]);
    }
}
