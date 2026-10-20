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

use Hallmark\Formatter\LineFormatter;
use Hallmark\Formatter\NormalizerFormatter;
use Hallmark\Level;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LineFormatter::class)]
#[CoversClass(NormalizerFormatter::class)]
final class LineFormatterTest extends TestCase
{
    public function testDefaultLayout(): void
    {
        $formatter = new LineFormatter(dateFormat: 'Y-m-d');
        $output    = $formatter->format($this->record(
            level: Level::Error,
            message: 'oops',
            context: ['user' => 7],
            channel: 'app',
        ));

        self::assertSame("[2026-07-28] app.ERROR: oops {\"user\":7} []\n", $output);
    }

    public function testDefaultDatetimeKeepsMicrosecondsWhileContextDatesDoNot(): void
    {
        $formatter = new LineFormatter(format: '%datetime% %context%');
        $output    = $formatter->format($this->record(
            context: ['at' => new \DateTimeImmutable('2026-07-28T09:00:00.5+00:00')],
            datetime: '2026-07-28T12:00:00.123456+00:00',
        ));

        self::assertSame('2026-07-28T12:00:00.123456+00:00 {"at":"2026-07-28T09:00:00+00:00"}', $output);
    }

    public function testNewlinesAreCollapsedByDefault(): void
    {
        $formatter = new LineFormatter(format: '%message%');

        self::assertSame('a b c', $formatter->format($this->record(message: "a\nb\r\nc")));
    }

    public function testInlineLineBreaksArePreservedWhenAllowed(): void
    {
        $formatter = new LineFormatter(format: '%message%', allowInlineLineBreaks: true);

        self::assertSame("a\nb", $formatter->format($this->record(message: "a\nb")));
    }

    public function testEmptyContextAndExtraCanBeSuppressed(): void
    {
        $formatter = new LineFormatter(
            format: '%message%%context%%extra%',
            ignoreEmptyContextAndExtra: true,
        );

        self::assertSame('hi', $formatter->format($this->record(message: 'hi')));
    }

    public function testExceptionContextIsNormalized(): void
    {
        $formatter = new LineFormatter(format: '%context%');
        $output    = $formatter->format($this->record(context: ['exception' => new \RuntimeException('kaboom', 42)]));

        self::assertStringContainsString('RuntimeException', $output);
        self::assertStringContainsString('kaboom', $output);
        self::assertStringContainsString('42', $output);
    }
}
