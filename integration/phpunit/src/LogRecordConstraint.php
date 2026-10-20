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

namespace Hallmark\PHPUnit;

use function array_filter;
use function array_values;
use function implode;
use function is_array;
use function preg_match;
use function in_array;
use function sprintf;
use function str_contains;

use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\LogRecord;
use PHPUnit\Framework\Constraint\Constraint;

/**
 * A PHPUnit constraint that passes when the captured records contain at least
 * one entry of the expected level that also satisfies the optional message
 * criteria (exact / substring / regex / predicate).
 *
 * On failure it lists every record that WAS captured, so the message tells you
 * what the code logged instead — no re-running with a var_dump.
 *
 * The subject may be a {@see TestHandler} or a plain `list<LogRecord>`. With
 * `$absent` it passes only when no such record was captured.
 */
final class LogRecordConstraint extends Constraint
{
    /**
     * @param null|\Closure(LogRecord): bool $predicate
     */
    public function __construct(
        private readonly Level $level,
        private readonly ?string $message = null,
        private readonly ?string $contains = null,
        private readonly ?string $pattern = null,
        private readonly ?\Closure $predicate = null,
        private readonly bool $absent = false,
    ) {}

    #[\Override]
    public function toString(): string
    {
        $name = $this->level->getName();

        return sprintf(
            '%s %s %s record%s',
            $this->absent ? 'did not log' : 'logged',
            in_array($name[0], ['A', 'E', 'I', 'O', 'U'], true) ? 'an' : 'a',
            $name,
            $this->criteria(),
        );
    }

    #[\Override]
    protected function matches(mixed $other): bool
    {
        foreach ($this->recordsOf($other) as $record) {
            if ($this->recordMatches($record)) {
                return !$this->absent;
            }
        }

        return $this->absent;
    }

    #[\Override]
    protected function failureDescription(mixed $other): string
    {
        return 'the code under test ' . $this->toString();
    }

    #[\Override]
    protected function additionalFailureDescription(mixed $other): string
    {
        $records = $this->recordsOf($other);

        if ($records === []) {
            return 'Nothing was logged.';
        }

        $lines = [];

        foreach ($records as $record) {
            $lines[] = sprintf('  %s: %s', $record->level->getName(), $record->message);
        }

        return sprintf("Records that were logged:\n%s", implode("\n", $lines));
    }

    private function recordMatches(LogRecord $record): bool
    {
        return $record->level === $this->level
            && ($this->message === null || $record->message === $this->message)
            && ($this->contains === null || str_contains($record->message, $this->contains))
            && ($this->pattern === null || preg_match($this->pattern, $record->message) === 1)
            && (!$this->predicate instanceof \Closure || ($this->predicate)($record) === true);
    }

    /**
     * @return list<LogRecord>
     */
    private function recordsOf(mixed $other): array
    {
        if ($other instanceof TestHandler) {
            return $other->getRecords();
        }

        if (is_array($other)) {
            return array_values(array_filter(
                $other,
                static fn(mixed $record): bool => $record instanceof LogRecord,
            ));
        }

        return [];
    }

    private function criteria(): string
    {
        return match (true) {
            $this->message !== null              => sprintf(' with message "%s"', $this->message),
            $this->contains !== null             => sprintf(' whose message contains "%s"', $this->contains),
            $this->pattern !== null              => sprintf(' whose message matches %s', $this->pattern),
            $this->predicate instanceof \Closure => ' matching the given predicate',
            default                              => '',
        };
    }
}
