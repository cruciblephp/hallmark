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

use Hallmark\Formatter\FormatterInterface;

/**
 * A handler whose output string is produced by a swappable formatter.
 */
interface FormattableHandlerInterface extends HandlerInterface
{
    public function setFormatter(FormatterInterface $formatter): HandlerInterface;

    public function getFormatter(): FormatterInterface;
}
