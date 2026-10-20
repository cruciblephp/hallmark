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

namespace Hallmark\Event;

use function implode;
use function preg_match;
use function sprintf;
use function trim;

use Hallmark\Exception\InvalidArgumentException;

/**
 * Validates one or more {@see EventCode} enums against a lightweight schema: a
 * code format, global uniqueness, and non-empty messages.
 *
 * This is the type-safe successor to catalogs that could only be checked by
 * regexing the source: because every event is an enum case, the whole catalog is
 * enumerable via `::cases()` with no reflection. Run {@see self::assertValid()}
 * in a test (or a boot check) to keep the catalog honest as it grows.
 */
final class EventCatalog
{
    /** Default shape: an upper-case domain prefix, a hyphen, then digits — e.g. `PAY-1001`. */
    public const string DEFAULT_CODE_PATTERN = '/^[A-Z][A-Z0-9]*-\d{3,}$/';

    /**
     * @param  iterable<class-string<EventCode&\UnitEnum>> $enums
     * @return list<string>                                human-readable violations; empty when valid
     */
    public static function validate(iterable $enums, string $codePattern = self::DEFAULT_CODE_PATTERN): array
    {
        $errors = [];

        /** @var array<string, string> $seen code => where it was first defined */
        $seen = [];

        foreach ($enums as $enum) {
            foreach ($enum::cases() as $case) {
                $where = $enum . '::' . $case->name;
                $code  = $case->code();

                if (isset($seen[$code])) {
                    $errors[] = sprintf('Duplicate code "%s" on %s (already defined on %s).', $code, $where, $seen[$code]);
                } else {
                    $seen[$code] = $where;
                }

                if (preg_match($codePattern, $code) !== 1) {
                    $errors[] = sprintf('Code "%s" on %s does not match %s.', $code, $where, $codePattern);
                }

                if (trim($case->message()) === '') {
                    $errors[] = sprintf('Empty message on %s.', $where);
                }
            }
        }

        return $errors;
    }

    /**
     * @param iterable<class-string<EventCode&\UnitEnum>> $enums
     *
     * @throws InvalidArgumentException when the catalog violates the schema
     */
    public static function assertValid(iterable $enums, string $codePattern = self::DEFAULT_CODE_PATTERN): void
    {
        $errors = self::validate($enums, $codePattern);

        if ($errors !== []) {
            throw new InvalidArgumentException("Invalid event catalog:\n - " . implode("\n - ", $errors));
        }
    }
}
