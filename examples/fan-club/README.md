# The fan-club example

Laravents' example application is a Beatles fan-club site, built by Luciano, a fan and a developer. This is its logging, with Hallmark and nothing else: the same cast, the same three modules, and an evening of what they log.

```bash
php examples/fan-club/run.php
```

- **Band** gives nicknames and changes its lineup. John calls George "The Quiet One"; Luciano, a fan, asks to give John a nickname and is refused, because only a member gives one; and in August 1962 Pete leaves and Ringo takes the drums. Each is an event of the catalog in `BandEvent`, with its code on the line.
- **FanClub** welcomes Luciano. His signup's email and password reach the log masked.
- **Press** prints the headlines on a worker: one logger for the whole queue, a correlation id per job, and `reset()` between jobs. The second headline is longer than the column and fails.

The site's logger (`src/Site.php`) writes every info line to a daily file, and sends to standard output, as JSON, only the lead-up of what failed: the failing job's own lines, its debug line included, and nothing from the jobs that went well.

The test suite runs the evening and checks its output: [`tests/Examples/FanClubExampleTest.php`](../../tests/Examples/FanClubExampleTest.php).
