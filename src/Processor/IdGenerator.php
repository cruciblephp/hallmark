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

namespace Hallmark\Processor;

/**
 * Where {@see UidProcessor} gets an id when the application has its own kind: a ULID, a UUID, or an id it already
 * holds. Without one, the processor mints random hex.
 */
interface IdGenerator
{
    /** A new id, never empty. */
    public function generate(): string;
}
