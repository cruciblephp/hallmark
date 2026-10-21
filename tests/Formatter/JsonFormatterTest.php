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

namespace Hallmark\Tests\Formatter;

use function array_keys;
use function explode;
use function ini_set;
use function json_decode;

use Hallmark\Formatter\BatchMode;
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Formatter\NormalizerFormatter;
use Hallmark\Level;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonFormatter::class)]
#[CoversClass(NormalizerFormatter::class)]
final class JsonFormatterTest extends TestCase
{
    public function testFormatsOneRecordAsJsonObject(): void
    {
        $formatter = new JsonFormatter();
        $output    = $formatter->format($this->record(
            level: Level::Info,
            message: 'hello',
            context: ['k' => 'v'],
            channel: 'app',
        ));

        self::assertStringEndsWith("\n", $output);

        $decoded = json_decode(trim($output), true);

        self::assertMatchesShape('array{message: string, level: int, level_name: string, channel: string, datetime: string, context: array<string, mixed>, extra: array<mixed>}', $decoded);
        self::assertSame('hello', $decoded['message']);
        self::assertSame(200, $decoded['level']);
        self::assertSame('INFO', $decoded['level_name']);
        self::assertSame('app', $decoded['channel']);
        self::assertSame(['k' => 'v'], $decoded['context']);
    }

    public function testBatchAsJsonArray(): void
    {
        $formatter = new JsonFormatter(batchMode: BatchMode::Json);
        $output    = $formatter->formatBatch([$this->record(message: 'a'), $this->record(message: 'b')]);

        $decoded = json_decode($output, true);

        self::assertMatchesShape('list<array{message: string}>', $decoded);
        self::assertCount(2, $decoded);
        self::assertSame('a', $decoded[0]['message']);
        self::assertSame('b', $decoded[1]['message']);
    }

    public function testBatchAsNewlines(): void
    {
        $formatter = new JsonFormatter(batchMode: BatchMode::Newlines);
        $output    = $formatter->formatBatch([$this->record(message: 'a'), $this->record(message: 'b')]);

        self::assertCount(2, explode("\n", $output));
    }

    public function testFlatScalarContextRoundTripsThroughTheFastPath(): void
    {
        $formatter = new JsonFormatter();
        $output    = $formatter->format($this->record(context: [
            'id' => 42, 'name' => 'sam', 'active' => true, 'note' => null,
        ]));

        $decoded = json_decode(trim($output), true);

        self::assertMatchesShape('array{context: array<string, mixed>}', $decoded);
        self::assertSame(['id' => 42, 'name' => 'sam', 'active' => true, 'note' => null], $decoded['context']);
    }

    public function testNestedArrayStillTakesTheNormalizingPath(): void
    {
        $formatter = new JsonFormatter();
        $output    = $formatter->format($this->record(context: [
            'event' => ['at' => new \DateTimeImmutable('2026-07-28T00:00:00+00:00')],
        ]));

        // The nested DateTime must be rendered to a string, not left as an object.
        self::assertMatchesShape('array{context: array{event: array{at: string}}}', json_decode(trim($output), true));
    }

    public function testExceptionIsNormalizedWithTrace(): void
    {
        $formatter = new JsonFormatter(includeStacktraces: true);
        $output    = $formatter->format($this->record(context: ['exception' => new \RuntimeException('boom')]));

        $decoded = json_decode(trim($output), true);

        self::assertMatchesShape('array{context: array{exception: array{class: string, trace: list<string>}}}', $decoded);
        self::assertSame('RuntimeException', $decoded['context']['exception']['class']);
    }

    public function testStacktracesAreOffByDefaultAsInMonolog(): void
    {
        $decoded = json_decode(trim((new JsonFormatter())->format($this->record(context: ['exception' => new \RuntimeException('boom')]))), true);

        self::assertMatchesShape('array{context: array{exception: array<string, mixed>}}', $decoded);
        self::assertArrayNotHasKey('trace', $decoded['context']['exception']);
    }

    public function testATraceFrameIsFileAndLineAndNeverItsArguments(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');

        try {
            $e = $this->throwWith('hunter2-secret');
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }

        $output  = (new JsonFormatter(includeStacktraces: true))->format($this->record(context: ['exception' => $e]));
        $decoded = json_decode(trim($output), true);

        self::assertStringNotContainsString('hunter2-secret', $output);
        self::assertMatchesShape('array{context: array{exception: array{trace: non-empty-list<string>}}}', $decoded);
        self::assertSame(__FILE__ . ':' . self::$throwLine, $decoded['context']['exception']['trace'][0]);
    }

    public function testABasePathIsStrippedAndTheTraceCapped(): void
    {
        $formatter = (new JsonFormatter(includeStacktraces: true))->setBasePath(\dirname(__DIR__, 2) . '/')->setMaxTraceLength(1);
        $decoded   = json_decode(trim($formatter->format($this->record(context: ['exception' => $this->throwWith('x')]))), true);

        self::assertMatchesShape('array{context: array{exception: array{file: string, trace: list<string>}}}', $decoded);
        self::assertStringStartsWith('tests/Formatter/JsonFormatterTest.php:', $decoded['context']['exception']['file']);
        self::assertSame(['tests/Formatter/JsonFormatterTest.php:' . self::$throwLine], $decoded['context']['exception']['trace']);
    }

    public function testKeysAreInMonologsOrder(): void
    {
        $decoded = json_decode(trim((new JsonFormatter())->format($this->record())), true);

        self::assertIsArray($decoded);
        self::assertSame(['message', 'context', 'level', 'level_name', 'channel', 'datetime', 'extra'], array_keys($decoded));
    }

    public function testEmptyContextAndExtraAreJsonObjects(): void
    {
        $output = (new JsonFormatter())->format($this->record());

        self::assertStringContainsString('"context":{}', $output);
        self::assertStringContainsString('"extra":{}', $output);
    }

    public function testRecordDatetimeKeepsMicrosecondsWhileContextDatesDoNot(): void
    {
        $output = (new JsonFormatter())->format($this->record(
            context: ['at' => new \DateTimeImmutable('2026-07-28T09:00:00.5+00:00')],
            datetime: '2026-07-28T12:00:00.123456+00:00',
        ));

        self::assertStringContainsString('"datetime":"2026-07-28T12:00:00.123456+00:00"', $output);
        self::assertStringContainsString('"at":"2026-07-28T09:00:00+00:00"', $output);
    }

    public function testACustomDateFormatAppliesToRecordAndContextDates(): void
    {
        $output = (new JsonFormatter(dateFormat: 'Y-m-d'))->format($this->record(
            context: ['at' => new \DateTimeImmutable('2026-07-27T09:00:00+00:00')],
        ));

        self::assertStringContainsString('"datetime":"2026-07-28"', $output);
        self::assertStringContainsString('"at":"2026-07-27"', $output);
    }

    private static int $throwLine = 0;

    /** An exception whose top frame is the call below, with `$secret` as its argument. */
    private function throwWith(string $secret): \RuntimeException
    {
        try {
            self::$throwLine = __LINE__ + 1;
            (static fn (string $password) => throw new \RuntimeException('wrong password'))($secret);
        } catch (\RuntimeException $e) {
            return $e;
        }
    }

    public function testJsonSerializableOutputIsLeftToTheEncoderAsInMonolog(): void
    {
        $value = new class implements \JsonSerializable {
            public function jsonSerialize(): mixed
            {
                return ['ratio' => \NAN, 'at' => new \DateTimeImmutable('2026-07-28T00:00:00+00:00')];
            }
        };

        $output = (new JsonFormatter())->format($this->record(context: ['value' => $value]));

        self::assertStringContainsString('"value":{"ratio":0,"at":{"date":"2026-07-28 00:00:00.000000","timezone_type":1,"timezone":"+00:00"}}', $output);
    }
}
