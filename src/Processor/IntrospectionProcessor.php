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

use function array_merge;
use function array_shift;
use function debug_backtrace;
use function str_contains;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\LogRecord;

/**
 * Adds the call site — `extra.file/line/class/function` — of the code that
 * emitted the record.
 *
 * The library's own frames (this processor, the logger, the PSR-3 trait) are
 * skipped so the reported location is the caller's, not our plumbing. Because
 * walking the stack is not free, it only runs at or above `$level`.
 */
final readonly class IntrospectionProcessor implements ProcessorInterface
{
    /**
     * Frames captured on top of `$skipStackFramesCount`: enough to cover this
     * processor, the logger/handler plumbing and the target call site, with a
     * little slack for a handler-level processor chain.
     */
    private const int FRAME_HEADROOM = 12;

    /** @var list<string> */
    private array $skipClassesPartials;

    /**
     * @param list<string> $skipClassesPartials additional class-name fragments whose frames should be skipped
     */
    public function __construct(
        private Level $level = Level::Debug,
        array $skipClassesPartials = [],
        private int $skipStackFramesCount = 0,
    ) {
        // Precise internal prefixes only — matching the bare namespace would
        // also swallow a caller that happens to live under Hallmark\
        // (a bundled integration, or this library's own test suite).
        $this->skipClassesPartials = array_merge([
            Logger::class,
            'Hallmark\\Handler\\',
            'Hallmark\\Processor\\',
        ], $skipClassesPartials);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        if ($record->level->isLowerThan($this->level)) {
            return $record;
        }

        // The call site sits just past this library's own frames, which are at
        // the top of a most-recent-first backtrace. Capping the depth keeps the
        // cost constant instead of scaling with total stack depth — a win when
        // logging from deep in a call tree. The cap covers: this processor, the
        // logger/handler plumbing, any caller-requested skip, and the target.
        $limit = self::FRAME_HEADROOM + $this->skipStackFramesCount;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit);

        // Drop this processor's own frame, then skip over every frame belonging
        // to the library until we reach the caller that actually logged.
        array_shift($trace);

        $i = 0;

        while (isset($trace[$i]['class']) && $this->isSkipped($trace[$i]['class'])) {
            $i++;
        }

        $i += $this->skipStackFramesCount;

        // file/line come from the frame *below* the call boundary (where the
        // logging method was invoked); class/function from the caller itself.
        return $record->with(extra: [
            ...$record->extra,
            'file'     => $trace[$i - 1]['file'] ?? null,
            'line'     => $trace[$i - 1]['line'] ?? null,
            'class'    => $trace[$i]['class']    ?? null,
            'function' => $trace[$i]['function'] ?? null,
        ]);
    }

    private function isSkipped(string $class): bool
    {
        foreach ($this->skipClassesPartials as $partial) {
            if (str_contains($class, $partial)) {
                return true;
            }
        }

        return false;
    }
}
