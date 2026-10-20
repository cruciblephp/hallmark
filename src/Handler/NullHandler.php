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
 * Discards every record it is asked to handle.
 *
 * Placed at the top of a stack it acts as a floor: records at or above its
 * level are swallowed (and stop bubbling), so lower handlers never see them —
 * handy for silencing a channel in tests without rewiring the logger.
 */
final class NullHandler extends AbstractHandler
{
    public function __construct(Level $level = Level::Debug)
    {
        parent::__construct($level, false);
    }

    public function handle(LogRecord $record): bool
    {
        return $record->level->value >= $this->level->value;
    }
}
