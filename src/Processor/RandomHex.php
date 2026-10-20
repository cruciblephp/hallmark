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

use function bin2hex;
use function random_bytes;
use function substr;

/**
 * Random lowercase hex ids for {@see UidProcessor} and {@see CorrelationIdProcessor}.
 *
 * @internal
 */
final class RandomHex
{
    /** @param positive-int $length */
    public static function generate(int $length): string
    {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    }
}
