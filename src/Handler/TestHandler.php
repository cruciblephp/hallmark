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

use function array_filter;
use function array_values;
use function str_contains;

use Hallmark\Level;
use Hallmark\LogRecord;

/**
 * Captures every record it receives instead of writing it anywhere, so a test
 * can assert on what the code under test logged.
 *
 *   $handler = new TestHandler();
 *   $logger  = new Logger('test', [$handler]);
 *   $service->run();
 *   self::assertTrue($handler->hasErrorThatContains('timeout'));
 *
 * This lives in the core (not the PHPUnit package) and depends on nothing but
 * this library, so it keeps working under any test framework. The PHPUnit
 * `AssertLog` trait is a thin layer of sugar on top of these `has*` queries.
 */
final class TestHandler extends AbstractProcessingHandler
{
    /** @var list<LogRecord> */
    private array $records = [];

    /** @return list<LogRecord> */
    public function getRecords(): array
    {
        return $this->records;
    }

    /** Drop every captured record — handy in a `setUp()` between cases. */
    public function clear(): void
    {
        $this->records = [];
    }

    /** @return list<LogRecord> */
    public function getRecordsForLevel(Level $level): array
    {
        return array_values(array_filter(
            $this->records,
            static fn(LogRecord $record): bool => $record->level === $level,
        ));
    }

    public function hasRecords(Level $level): bool
    {
        return $this->getRecordsForLevel($level) !== [];
    }

    public function hasRecord(Level $level, string $message): bool
    {
        return $this->matches($level, static fn(LogRecord $record): bool => $record->message === $message);
    }

    public function hasRecordThatContains(Level $level, string $substring): bool
    {
        return $this->matches($level, static fn(LogRecord $record): bool => str_contains($record->message, $substring));
    }

    public function hasErrorThatContains(string $substring): bool
    {
        return $this->hasRecordThatContains(Level::Error, $substring);
    }

    public function hasWarningThatContains(string $substring): bool
    {
        return $this->hasRecordThatContains(Level::Warning, $substring);
    }

    protected function write(LogRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * @param callable(LogRecord): bool $predicate
     */
    private function matches(Level $level, callable $predicate): bool
    {
        foreach ($this->getRecordsForLevel($level) as $record) {
            if ($predicate($record)) {
                return true;
            }
        }

        return false;
    }
}
