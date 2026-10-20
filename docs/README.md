# Hallmark Manual

The reference for Hallmark. The [README](../README.md) is the short tour; these pages are the rest, in reading order. Every PHP sample on them runs as written in the test suite ([`tests/Docs/DocsSamplesTest.php`](../tests/Docs/DocsSamplesTest.php)), and the output shown under a sample is the output it prints, timestamps aside. A configuration fragment that cannot run on its own names the test that holds it instead.

1. [Installation](installation.md): requirements, `composer require`, the first logger, what each integration needs.
2. [The logger](logger.md): building a `Logger`, the PSR-3 methods and `log()`, levels, records, the handler and processor stacks, bubbling, lazy messages, child loggers.
3. [Handlers](handlers.md): writing to streams, files, syslog and memory, the decorators, a handler's own formatter and processors, writing a handler.
4. [Formatters](formatters.md): `LineFormatter` and its placeholders, `JsonFormatter` and batches, how values are normalized, dates.
5. [Processors](processors.md): placeholders, call sites, memory, ids, redaction, order, a closure as a processor.
6. [The event catalog](event-catalog.md): `EventCode` enums, `Logger::event()`, validating a catalog with `EventCatalog`.
7. [Laravel](laravel.md): the `hallmark` driver, its channel keys, Laravel's Context on every record, what a channel cannot do.
8. [Testing](testing.md): `TestHandler`, the `AssertLog` assertions, running them on Crucible or PHPUnit.
9. [Long-running workers](long-running-workers.md): `reset()` and `Resettable`, closing, and the exception handler.
10. [Reference](reference.md): every class, its constructor and its public methods.

Two examples, in the theme of Laravents' Beatles fan-club site, put the pieces together: [the fan club with Hallmark alone](../examples/fan-club/README.md), and [the fan club on Laravents](../examples/laravents/README.md), whose log lines carry Laravents' message ids.
