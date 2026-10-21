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

namespace FanClub;

use Hallmark\Formatter\JsonFormatter;
use Hallmark\Handler\FingersCrossedHandler;
use Hallmark\Handler\RotatingFileHandler;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Processor\CorrelationIdProcessor;
use Hallmark\Processor\PsrLogMessageProcessor;
use Hallmark\Processor\RedactionProcessor;

/**
 * The site's logger: every info line to a daily file, and, on standard output as
 * JSON, the whole lead-up of anything that fails, debug lines included.
 */
final readonly class Site
{
    public Logger $log;

    public CorrelationIdProcessor $correlation;

    public function __construct(string $logDirectory)
    {
        $file = new RotatingFileHandler($logDirectory . '/fan-club.log', maxFiles: 14, level: Level::Info);

        $stdout = new StreamHandler('php://stdout');
        $stdout->setFormatter(new JsonFormatter(includeStacktraces: true));

        $this->correlation = new CorrelationIdProcessor();
        $this->log         = new Logger('fan-club', [$file, new FingersCrossedHandler($stdout, activationLevel: Level::Error)], [$this->correlation]);

        // Pushed last, so it runs first: secrets are masked before a message is written from them.
        $this->log->pushProcessor(new PsrLogMessageProcessor());
        $this->log->pushProcessor(new RedactionProcessor([...RedactionProcessor::DEFAULT_KEYS, 'email']));
    }
}
