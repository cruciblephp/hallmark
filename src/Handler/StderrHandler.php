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

/**
 * Convenience {@see StreamHandler} bound to `php://stderr` — the right default
 * for CLI tools and containers, where logs belong on the error stream and
 * stdout is reserved for program output.
 */
final class StderrHandler extends StreamHandler
{
    public function __construct(Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct('php://stderr', $level, $bubble);
    }
}
