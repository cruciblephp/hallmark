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

namespace Hallmark\Handler;

use Hallmark\Level;
use Hallmark\LogRecord;

/**
 * Common ground for handlers: a minimum {@see Level} threshold and a bubble
 * flag. Subclasses only have to implement {@see HandlerInterface::handle()}.
 */
abstract class AbstractHandler implements HandlerInterface
{
    public function __construct(
        protected Level $level = Level::Debug,
        protected bool $bubble = true,
    ) {}

    public function isHandling(LogRecord $record): bool
    {
        return $record->level->value >= $this->level->value;
    }

    public function handleBatch(array $records): void
    {
        foreach ($records as $record) {
            $this->handle($record);
        }
    }

    public function close(): void {}

    public function setLevel(Level $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getLevel(): Level
    {
        return $this->level;
    }

    public function setBubble(bool $bubble): self
    {
        $this->bubble = $bubble;

        return $this;
    }

    public function getBubble(): bool
    {
        return $this->bubble;
    }
}
