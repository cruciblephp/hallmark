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

use function mb_strlen;

use Hallmark\Logger;

use LengthException;

/** Prints a headline about a nickname; a nickname longer than the column is not printed. */
final readonly class Press
{
    /** The column's width, Laravents' own limit on a nickname. */
    public const int COLUMN = 40;

    public function __construct(private Logger $log) {}

    public function printHeadline(string $publication, Person $member, string $nickname): void
    {
        $this->log->debug('Setting {publication} for {member}', ['publication' => $publication, 'member' => $member->name]);

        if (mb_strlen($nickname) > self::COLUMN) {
            throw new LengthException(\sprintf('"%s" is longer than the %d-character column.', $nickname, self::COLUMN));
        }

        $this->log->event(PressEvent::HeadlinePrinted, ['publication' => $publication, 'member' => $member->name, 'nickname' => $nickname]);
    }
}
