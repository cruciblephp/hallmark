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

use Hallmark\LogRecord;

/**
 * Base for the common handler shape: run the handler's own processors, format
 * the record, then write it. Subclasses implement only {@see self::write()}.
 */
abstract class AbstractProcessingHandler extends AbstractHandler implements ProcessableHandlerInterface, FormattableHandlerInterface
{
    use ProcessableHandlerTrait;
    use FormattableHandlerTrait;

    public function handle(LogRecord $record): bool
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        if ($this->processors !== []) {
            $record = $this->processRecord($record);
        }

        $record->formatted = $this->getFormatter()->format($record);

        $this->write($record);

        return $this->bubble === false;
    }

    /** Persist the already-formatted record to this handler's destination. */
    abstract protected function write(LogRecord $record): void;
}
