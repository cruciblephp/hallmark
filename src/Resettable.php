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

namespace Hallmark;

/**
 * A handler or processor that holds per-request state — a buffer, a counter, a
 * correlation id — and can be returned to a clean slate.
 *
 * On a long-lived runtime (Octane, RoadRunner, a queue worker) the same logger
 * instance serves many requests; calling {@see Logger::reset()} at each boundary
 * flushes buffers and rolls ids so nothing leaks from one request into the next.
 * Distinct from `close()`, which tears the handler down for good.
 */
interface Resettable
{
    public function reset(): void;
}
