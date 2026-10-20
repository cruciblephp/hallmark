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

// An evening on the fan-club site: php examples/fan-club/run.php [log directory]

use FanClub\Band;
use FanClub\FanClub;
use FanClub\Instrument;
use FanClub\Person;
use FanClub\Press;
use FanClub\Site;
use FanClub\Worker;

require __DIR__ . '/../../vendor/autoload.php';

$logDirectory = $argv[1] ?? __DIR__ . '/var/logs';
$site         = new Site($logDirectory);

$john    = new Person('John', Instrument::Guitar);
$george  = new Person('George', Instrument::Guitar);
$pete    = new Person('Pete', Instrument::Drums);
$luciano = new Person('Luciano');

// The band, in August 1962.
$site->correlation->reset('band-session');
$band  = new Band($site->log);
$band->giveNickname($john, $george, 'The Quiet One');
$band->giveNickname($luciano, $john, 'The Smart One');
$ringo = $band->replace($pete, new Person('Ringo'));

// A new fan signs up.
$site->correlation->reset('signup');
(new FanClub($site->log))->welcome($luciano, 'luciano@example.com', 'love-me-do');

// The press works through its queue, one job at a time.
(new Worker($site->log, $site->correlation, new Press($site->log)))->run([
    ['101', $george, 'The Quiet One'],
    ['102', $ringo, 'The one who joined in August 1962 to replace Pete on drums'],
    ['103', $ringo, 'Ringo'],
]);

$site->log->close();

foreach (glob($logDirectory . '/fan-club-*.log') ?: [] as $file) {
    echo "\n== ", basename($file), "\n", file_get_contents($file);
}
