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

use Hallmark\Event\EventCode;
use Hallmark\Logger;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * What a `hallmark` log channel hands to Laravel: a plain PSR-3 logger.
 *
 * Laravel's log manager calls `isHandling()`, `pushProcessor()` and
 * `getHandlers()` on a channel that has them, with Monolog's types. Hallmark's
 * {@see Logger} has methods of those names with its own types, so the channel
 * hides them behind PSR-3 and exposes the logger through {@see self::getLogger()}.
 * A `stack` channel takes {@see self::getHandlers()}: one Monolog handler that
 * forwards to the Hallmark logger.
 */
final readonly class ChannelLogger implements LoggerInterface
{
    use LoggerTrait;

    public function __construct(private Logger $logger) {}

    /** The Hallmark logger behind this channel, to push handlers or call `reset()`. */
    public function getLogger(): Logger
    {
        return $this->logger;
    }

    /**
     * What a `stack` channel collects from each of its channels.
     *
     * @return list<StackHandler>
     */
    public function getHandlers(): array
    {
        return [new StackHandler($this->logger)];
    }

    /**
     * The channel's processors run inside the Hallmark logger, none in the stack.
     *
     * @return list<never>
     */
    public function getProcessors(): array
    {
        return [];
    }

    /**
     * @param array<array-key, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * A catalogued event, as {@see Logger::event()} writes it: its level, its message and its `event_code`. Laravel's
     * `Illuminate\Log\Logger` passes a call it does not know to the channel, so `Log::channel('hallmark')->event(...)`
     * arrives here.
     *
     * @param array<array-key, mixed> $context
     */
    public function event(EventCode $event, array $context = []): void
    {
        $this->logger->event($event, $context);
    }
}
