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

use Psr\Log\InvalidArgumentException as PsrInvalidArgumentException;

/**
 * Extends the PSR-3 exception so that an unknown log level thrown from here is
 * still caught by code that only knows about {@see \Psr\Log\InvalidArgumentException}.
 */
final class InvalidArgumentException extends PsrInvalidArgumentException implements Exception {}
