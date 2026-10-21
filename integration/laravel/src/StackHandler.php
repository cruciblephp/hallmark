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

use Hallmark\Level;
use Hallmark\Logger;
use Monolog\Handler\HandlerInterface;
use Monolog\LogRecord;

/**
 * The Monolog handler a `stack` channel holds for a `hallmark` channel: each
 * record goes on to the Hallmark logger, with its level, message, context and
 * time. The stack's `extra` stays behind; the channel's own processors run.
 */
final readonly class StackHandler implements HandlerInterface
{
    public function __construct(private Logger $logger) {}

    public function isHandling(LogRecord $record): bool
    {
        return $this->logger->isHandling(Level::from($record->level->value));
    }

    public function handle(LogRecord $record): bool
    {
        $this->logger->addRecord(Level::from($record->level->value), $record->message, $record->context, $record->datetime);

        return false;
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    public function close(): void
    {
        $this->logger->close();
    }
}
