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

namespace Hallmark\Exception;

/**
 * Marker interface implemented by every exception this library throws, so a
 * consumer can `catch (\Hallmark\Exception\Exception $e)` regardless
 * of which SPL parent the concrete class extends.
 */
interface Exception extends \Throwable {}
