# Testing

## `TestHandler`

`TestHandler` keeps every record it takes instead of writing it, so a test can ask what the code under test logged. It lives in the core and depends on nothing else, so it works under any test framework:

```php
use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;

$handler = new TestHandler();
$log = new Logger('shop', [$handler]);

$log->warning('payment retry scheduled');
$log->error('gateway declined', ['code' => 402]);

echo var_export($handler->hasRecord(Level::Warning, 'payment retry scheduled'), true), "\n";
echo var_export($handler->hasErrorThatContains('declined'), true), "\n";
echo var_export($handler->hasRecords(Level::Critical), true), "\n";
echo count($handler->getRecords()), "\n";
```

```text
true
true
false
2
```

| Method | Answers |
|---|---|
| `getRecords()` | Every record, in order |
| `getRecordsForLevel(Level)` | The records of one level |
| `hasRecords(Level)` | Whether any record has that level |
| `hasRecord(Level, string)` | Whether a record of that level has exactly that message |
| `hasRecordThatContains(Level, string)` | Whether a record of that level has a message containing the text |
| `hasErrorThatContains(string)`, `hasWarningThatContains(string)` | The same, for `Error` and `Warning` |
| `clear()` | Forgets everything, for a `setUp()` |

## `AssertLog`

The `AssertLog` trait turns those questions into assertions whose failure says what *was* logged. It is written against PHPUnit's API, so it runs in a PHPUnit suite and, unchanged, in a Crucible one:

```php
use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\LogRecord;
use Hallmark\PHPUnit\AssertLog;
use PHPUnit\Framework\TestCase;

final class CheckoutTest extends TestCase
{
    use AssertLog;

    public function testItWarnsOnRetry(): void
    {
        $handler = new TestHandler();
        $log = new Logger('shop', [$handler]);

        $log->warning('payment retry scheduled');
        $log->error('gateway declined', ['code' => 402]);

        $this->assertLogged($handler, Level::Warning, 'payment retry scheduled');
        $this->assertLoggedThatContains($handler, Level::Warning, 'retry');
        $this->assertLoggedThatMatches($handler, Level::Error, '/declined/');
        $this->assertLoggedThatPasses($handler, Level::Error, static fn (LogRecord $record): bool => $record->context['code'] === 402);
        $this->assertNotLogged($handler, Level::Critical, 'gateway declined');
    }
}
```

<!-- not run: a test case; tests/PHPUnit/AssertLogTest.php holds it -->

Each assertion takes the handler, a level and one criterion, and an optional failure message last. A failure names the record that was expected, then lists the records that were captured:

```text
Failed asserting that the code under test logged an ERROR record whose message contains "boom".
Records that were logged:
  INFO: all good
```

`assertNotLogged()` fails with `… did not log an INFO record with message "all good".` when such a record was captured.

The constraint behind them, `LogRecordConstraint`, can be used directly with `assertThat()`. Its subject is a `TestHandler` or a plain list of `LogRecord`s.

## A logger in a test

Give the code under test a logger with a `TestHandler`, assert on it, and call `clear()` between cases if the handler is shared. A `NullHandler` silences a channel the test does not care about. To keep a failing test's log out of the output and in the assertion instead, prefer a `TestHandler` to a stream.
