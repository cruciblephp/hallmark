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

use Hallmark\Logger;
use Hallmark\Processor\CorrelationIdProcessor;
use Throwable;

/**
 * Runs the press's queue on one long-lived logger: each job gets its own
 * correlation id, a failure is logged and the next job goes on, and the logger is
 * reset between jobs so nothing carries over.
 */
final readonly class Worker
{
    public function __construct(
        private Logger $log,
        private CorrelationIdProcessor $correlation,
        private Press $press,
    ) {}

    /** @param list<array{string, Person, string}> $jobs publication, member, nickname */
    public function run(array $jobs): void
    {
        foreach ($jobs as [$publication, $member, $nickname]) {
            $this->correlation->reset('job-' . $publication);

            try {
                $this->press->printHeadline($publication, $member, $nickname);
            } catch (Throwable $e) {
                $this->log->event(PressEvent::PrintFailed, ['publication' => $publication, 'exception' => $e]);
            } finally {
                $this->log->reset();
            }
        }
    }
}
