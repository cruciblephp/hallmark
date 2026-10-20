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

use function closelog;
use function openlog;
use function syslog;

use const LOG_ALERT;
use const LOG_CRIT;
use const LOG_DEBUG;
use const LOG_EMERG;
use const LOG_ERR;
use const LOG_INFO;
use const LOG_NOTICE;
use const LOG_PID;
use const LOG_USER;
use const LOG_WARNING;

use Hallmark\Level;
use Hallmark\LogRecord;

/**
 * Sends records to the system logger via {@see syslog()}, mapping each
 * {@see Level} onto its RFC 5424 syslog priority.
 */
class SyslogHandler extends AbstractProcessingHandler
{
    public function __construct(
        private readonly string $ident,
        private readonly int $facility = LOG_USER,
        Level $level = Level::Debug,
        bool $bubble = true,
        private readonly int $logopts = LOG_PID,
    ) {
        parent::__construct($level, $bubble);
    }

    public function close(): void
    {
        closelog();
    }

    protected function write(LogRecord $record): void
    {
        openlog($this->ident, $this->logopts, $this->facility);

        syslog($this->toSyslogPriority($record->level), $record->formatted);
    }

    private function toSyslogPriority(Level $level): int
    {
        return match ($level) {
            Level::Debug     => LOG_DEBUG,
            Level::Info      => LOG_INFO,
            Level::Notice    => LOG_NOTICE,
            Level::Warning   => LOG_WARNING,
            Level::Error     => LOG_ERR,
            Level::Critical  => LOG_CRIT,
            Level::Alert     => LOG_ALERT,
            Level::Emergency => LOG_EMERG,
        };
    }
}
