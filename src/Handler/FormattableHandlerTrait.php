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
use Hallmark\Formatter\LineFormatter;

/**
 * Shared implementation of {@see FormattableHandlerInterface}. The formatter is
 * built lazily on first use so a handler that is never triggered constructs no
 * formatter at all.
 */
trait FormattableHandlerTrait
{
    protected ?FormatterInterface $formatter = null;

    public function setFormatter(FormatterInterface $formatter): HandlerInterface
    {
        $this->formatter = $formatter;

        return $this;
    }

    public function getFormatter(): FormatterInterface
    {
        return $this->formatter ??= $this->getDefaultFormatter();
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new LineFormatter();
    }
}
