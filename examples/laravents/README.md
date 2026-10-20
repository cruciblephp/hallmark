# The Laravents example

Laravents puts five metadata keys in Laravel's Context while it handles a message: `message_id`, `correlation_id`, `causation_id`, `actor_id` and `actor_guard`. A `hallmark` channel adds Context to every record's `extra`, so a line written inside a handler says which message it belongs to, which request started it, and which message caused it.

This is a slice of Laravents' Beatles fan-club site, the Band and FanClub modules, logging to a `hallmark` channel:

- `modules/Band`: the `GiveNickname` command; its handler logs "Nickname given" and publishes `NicknameGiven`.
- `modules/FanClub`: `ArchiveBandEvents`, which logs every Band event, as it does in Laravents' own fan club.

```bash
php examples/laravents/run.php
```

John gives George a nickname, with the request's correlation id. Both lines carry `req-42`; the event's line names the command's message id as its `causation_id` (each id is numbered here, since they are new on every run):

```text
[<date>] fan-club.INFO: Nickname given {"member_id":2,"nickname":"The Quiet One"} {"message_id":"<id-1>","correlation_id":"req-42"}
[<date>] fan-club.INFO: Band event {"event":"Modules\\Band\\Api\\NicknameGiven","message_id":"<id-2>"} {"message_id":"<id-2>","correlation_id":"req-42","causation_id":"<id-1>"}
```

The script boots a Laravel application with Orchestra Testbench, registers Laravents and Hallmark, and builds the handler registry from the two modules' classes. The test suite runs it and checks its output: [`tests/Examples/LaraventsExampleTest.php`](../../tests/Examples/LaraventsExampleTest.php).
