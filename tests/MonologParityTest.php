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

namespace Hallmark\Tests;

use function array_map;
use function fopen;
use function ini_set;
use function range;

use Hallmark\Formatter\BatchMode;
use Hallmark\Formatter\FormatterInterface;
use Hallmark\Formatter\JsonFormatter;
use Hallmark\Formatter\LineFormatter;
use Hallmark\Level;
use Hallmark\LogRecord;
use Hallmark\Tests\Fixtures\Answer;
use Hallmark\Tests\Fixtures\Suit;
use Monolog\Formatter\FormatterInterface as MonologFormatterInterface;
use Monolog\Formatter\JsonFormatter as MonologJsonFormatter;
use Monolog\Formatter\LineFormatter as MonologLineFormatter;
use Monolog\JsonSerializableDateTimeImmutable;
use Monolog\Level as MonologLevel;
use Monolog\LogRecord as MonologLogRecord;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The README's "the level values, JSON, lines and dates match Monolog's", held by
 * running the same record through both, for every kind of value and option.
 * The two differences the README names are held by their own tests below.
 */
#[CoversNothing]
final class MonologParityTest extends TestCase
{
    /**
     * @return array<string, array<array-key, mixed>>
     */
    private static function values(): array
    {
        $deep = 'leaf';

        for ($i = 0; $i < 12; $i++) {
            $deep = [$deep];
        }

        $chain = self::withArguments(false, static function (): \Throwable {
            $chain = new \RuntimeException('e0');

            for ($i = 1; $i < 13; $i++) {
                $chain = new \RuntimeException('e' . $i, $i, $chain);
            }

            return $chain;
        });

        $object    = new \stdClass();
        $object->a = 1;
        $object->d = new \DateTimeImmutable('2026-01-02T03:04:05+00:00');

        return [
            'scalars'           => ['n' => null, 't' => true, 'f' => false, 'i' => 0, 'fl' => 1.0, 'fl2' => 1.5, 's' => "multi\nline", 'u' => 'ü€', 'bad' => "\xB1\x31"],
            'non-finite floats' => ['nan' => \NAN, 'inf' => \INF, 'ninf' => -\INF],
            'nested'            => ['a' => ['b' => ['c' => 1.5, 'd' => [1, 2]]]],
            'too deep'          => ['deep' => $deep],
            'too many items'    => ['big' => range(1, 1003)],
            'dates'             => ['dti' => new \DateTimeImmutable('2026-01-02T03:04:05.5+02:00'), 'dt' => new \DateTime('2026-01-02T03:04:05+00:00')],
            'an exception'      => ['exception' => self::thrown()],
            'an internal frame' => ['exception' => self::thrownByTheEngine()],
            'a long chain'      => ['exception' => $chain],
            'jsonserializable'  => ['m' => new class implements \JsonSerializable {
                public function jsonSerialize(): mixed
                {
                    return ['amount' => 1.0, 'at' => new \DateTimeImmutable('2026-01-02T03:04:05+00:00'), 'r' => \NAN];
                }
            }],
            'stringable'        => ['n' => new class implements \Stringable {
                public function __toString(): string
                {
                    return 'sam';
                }
            }],
            'a failing stringable' => ['b' => new class implements \Stringable {
                public int $p = 1;

                public function __toString(): string
                {
                    throw new \RuntimeException('no string');
                }
            }],
            'an object'         => ['o' => $object],
            'a closure'         => ['c' => static fn (): int => 1],
            'enums'             => ['s' => Suit::Hearts, 'p' => Answer::Yes],
            'a resource'        => ['r' => fopen('php://memory', 'r')],
            'empty'             => [],
        ];
    }

    /**
     * @return array<string, array{\Closure(): FormatterInterface, \Closure(): MonologFormatterInterface}>
     */
    private static function formatters(): array
    {
        $keys = "%message% <%context.x%> <%extra.x%> <%context.nope%> %context% %extra%\n";

        return [
            'json'              => [static fn (): FormatterInterface => new JsonFormatter(), static fn (): MonologFormatterInterface => new MonologJsonFormatter()],
            'json, traces'      => [static fn (): FormatterInterface => new JsonFormatter(includeStacktraces: true), static fn (): MonologFormatterInterface => new MonologJsonFormatter(includeStacktraces: true)],
            'json, no empty'    => [static fn (): FormatterInterface => new JsonFormatter(ignoreEmptyContextAndExtra: true), static fn (): MonologFormatterInterface => new MonologJsonFormatter(ignoreEmptyContextAndExtra: true)],
            'json, a date'      => [static fn (): FormatterInterface => new JsonFormatter(dateFormat: 'Y-m-d H:i'), static fn (): MonologFormatterInterface => (new MonologJsonFormatter())->setDateFormat('Y-m-d H:i')],
            'json, a base path' => [static fn (): FormatterInterface => (new JsonFormatter(includeStacktraces: true))->setBasePath(__DIR__), static fn (): MonologFormatterInterface => (new MonologJsonFormatter(includeStacktraces: true))->setBasePath(__DIR__)],
            'json, one frame'   => [static fn (): FormatterInterface => (new JsonFormatter(includeStacktraces: true))->setMaxTraceLength(1), static fn (): MonologFormatterInterface => (new MonologJsonFormatter(includeStacktraces: true))->setMaxTraceLength(1)],
            'line'              => [static fn (): FormatterInterface => new LineFormatter(), static fn (): MonologFormatterInterface => new MonologLineFormatter()],
            'line, traces'      => [static fn (): FormatterInterface => new LineFormatter(includeStacktraces: true), static fn (): MonologFormatterInterface => new MonologLineFormatter(includeStacktraces: true)],
            'line, line breaks' => [static fn (): FormatterInterface => new LineFormatter(allowInlineLineBreaks: true), static fn (): MonologFormatterInterface => new MonologLineFormatter(allowInlineLineBreaks: true)],
            'line, no empty'    => [static fn (): FormatterInterface => new LineFormatter(ignoreEmptyContextAndExtra: true), static fn (): MonologFormatterInterface => new MonologLineFormatter(ignoreEmptyContextAndExtra: true)],
            'line, a base path' => [static fn (): FormatterInterface => (new LineFormatter(includeStacktraces: true))->setBasePath(__DIR__), static fn (): MonologFormatterInterface => (new MonologLineFormatter(includeStacktraces: true))->setBasePath(__DIR__)],
            'line, a format'    => [static fn (): FormatterInterface => new LineFormatter("%level% %level_name% %message%|%context%|%extra%\n", 'U'), static fn (): MonologFormatterInterface => new MonologLineFormatter("%level% %level_name% %message%|%context%|%extra%\n", 'U')],
            'line, keys'        => [static fn (): FormatterInterface => new LineFormatter($keys), static fn (): MonologFormatterInterface => new MonologLineFormatter($keys)],
            'line, keys, no empty' => [static fn (): FormatterInterface => new LineFormatter("%context.x%|%context%|%extra%\n", ignoreEmptyContextAndExtra: true), static fn (): MonologFormatterInterface => new MonologLineFormatter("%context.x%|%context%|%extra%\n", ignoreEmptyContextAndExtra: true)],
        ];
    }

    /**
     * @return iterable<string, array{\Closure(): FormatterInterface, \Closure(): MonologFormatterInterface, array<array-key, mixed>, array<array-key, mixed>}>
     */
    public static function cases(): iterable
    {
        $values = self::values() + ['keys' => ['x' => 'X', 'y' => 2]];

        foreach (self::formatters() as $formatter => [$hallmark, $monolog]) {
            foreach ($values as $value => $data) {
                yield $formatter . ', ' . $value . ' in context' => [$hallmark, $monolog, $data, []];
                yield $formatter . ', ' . $value . ' in extra'   => [$hallmark, $monolog, [], $data];
            }
        }
    }

    /**
     * @param \Closure(): FormatterInterface        $hallmark
     * @param \Closure(): MonologFormatterInterface $monolog
     * @param array<array-key, mixed>               $context
     * @param array<array-key, mixed>               $extra
     */
    #[DataProvider('cases')]
    public function testTheOutputIsMonologs(\Closure $hallmark, \Closure $monolog, array $context, array $extra): void
    {
        $at = new JsonSerializableDateTimeImmutable(true);

        self::assertSame(
            $monolog()->format(new MonologLogRecord($at, 'app', MonologLevel::Error, "it\nfailed", $context, $extra)),
            $hallmark()->format(new LogRecord($at, 'app', Level::Error, "it\nfailed", $context, $extra)),
        );
    }

    public function testABatchIsMonologs(): void
    {
        $at       = new JsonSerializableDateTimeImmutable(true);
        $hallmark = [new LogRecord($at, 'app', Level::Info, 'a'), new LogRecord($at, 'app', Level::Info, 'b', ['k' => 1])];
        $monolog  = [new MonologLogRecord($at, 'app', MonologLevel::Info, 'a'), new MonologLogRecord($at, 'app', MonologLevel::Info, 'b', ['k' => 1])];

        self::assertSame((new MonologJsonFormatter(MonologJsonFormatter::BATCH_MODE_JSON))->formatBatch($monolog), (new JsonFormatter(BatchMode::Json))->formatBatch($hallmark));
        self::assertSame((new MonologJsonFormatter(MonologJsonFormatter::BATCH_MODE_NEWLINES))->formatBatch($monolog), (new JsonFormatter(BatchMode::Newlines))->formatBatch($hallmark));
        self::assertSame((new MonologLineFormatter())->formatBatch($monolog), (new LineFormatter())->formatBatch($hallmark));
    }

    public function testTheLevelValuesAreMonologs(): void
    {
        foreach (Level::cases() as $level) {
            self::assertSame($level->getName(), MonologLevel::from($level->value)->getName());
        }
    }

    public function testALineTraceNeverCarriesTheArgumentsMonologsDoesUnderTheDevelopmentIni(): void
    {
        $e  = self::thrown(keepArguments: true);
        $at = new JsonSerializableDateTimeImmutable(true);

        $monolog  = (new MonologLineFormatter(includeStacktraces: true))->format(new MonologLogRecord($at, 'app', MonologLevel::Error, 'x', ['exception' => $e]));
        $hallmark = (new LineFormatter(includeStacktraces: true))->format(new LogRecord($at, 'app', Level::Error, 'x', ['exception' => $e]));

        self::assertStringContainsString("MonologParityTest::throwWith('", $monolog);
        self::assertStringContainsString('MonologParityTest::throwWith()', $hallmark);
        self::assertStringNotContainsString('hunter2', $hallmark);
    }

    public function testAPlaceholderInsideAValueIsWrittenAsLoggedMonologExpandsIt(): void
    {
        $at      = new JsonSerializableDateTimeImmutable(true);
        $context = ['note' => '%channel% and %context.x%'];

        $monolog  = (new MonologLineFormatter())->format(new MonologLogRecord($at, 'app', MonologLevel::Error, 'x', $context));
        $hallmark = (new LineFormatter())->format(new LogRecord($at, 'app', Level::Error, 'x', $context));

        self::assertStringContainsString('{"note":"app and "}', $monolog);
        self::assertStringContainsString('{"note":"%channel% and %context.x%"}', $hallmark);
    }

    /**
     * Runs `$make` with the frames' arguments kept or dropped, as PHP's
     * development or production ini does, whatever the ini the suite runs under.
     *
     * @param \Closure(): \Throwable $make
     */
    private static function withArguments(bool $keep, \Closure $make): \Throwable
    {
        $previous = ini_set('zend.exception_ignore_args', $keep ? '0' : '1');

        try {
            return $make();
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    private static function thrown(bool $keepArguments = false): \Throwable
    {
        return self::withArguments($keepArguments, static function (): \Throwable {
            try {
                self::throwWith('hunter2');
            } catch (\Throwable $e) {
                return $e;
            }
        });
    }

    private static function throwWith(string $secret): never
    {
        throw new \LogicException("outer\nline", 3, new \RuntimeException('inner ' . \strlen($secret)));
    }

    /** Thrown from a closure that `array_map()` calls, so its top frame has no file. */
    private static function thrownByTheEngine(): \Throwable
    {
        return self::withArguments(false, static function (): \Throwable {
            try {
                array_map(static fn () => throw new class ('anonymous') extends \InvalidArgumentException {}, [1]);
            } catch (\Throwable $e) {
                return $e;
            }

            throw new \LogicException('array_map() did not throw.');
        });
    }
}
