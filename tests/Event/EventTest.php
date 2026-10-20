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

namespace Hallmark\Tests\Event;

use function implode;

use Hallmark\Event\EventCatalog;
use Hallmark\Event\EventCode;
use Hallmark\Exception\InvalidArgumentException;
use Hallmark\Handler\TestHandler;
use Hallmark\Level;
use Hallmark\Logger;
use Hallmark\Tests\Fixtures\SampleEvent;
use Hallmark\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EventCatalog::class)]
#[CoversClass(Logger::class)]
final class EventTest extends TestCase
{
    public function testEventLogsAtItsOwnLevelAndStampsTheCode(): void
    {
        $handler = new TestHandler();
        $logger  = new Logger('app', [$handler]);

        $logger->event(SampleEvent::AuthorizationFailed, ['order' => 42]);

        $record = $this->lastRecord($handler);
        self::assertSame(Level::Error, $record->level);
        self::assertSame('Payment authorization failed', $record->message);
        self::assertSame('PAY-1001', $record->context['event_code']);
        self::assertSame(42, $record->context['order']);
    }

    public function testValidCatalogHasNoViolations(): void
    {
        self::assertSame([], EventCatalog::validate([SampleEvent::class]));
        EventCatalog::assertValid([SampleEvent::class]); // does not throw
    }

    public function testValidatorCatchesDuplicateCodesAcrossEnums(): void
    {
        // DuplicateEvent reuses PAY-1001 from SampleEvent.
        $errors = EventCatalog::validate([SampleEvent::class, DuplicateEvent::class]);

        self::assertNotSame([], $errors);
        self::assertStringContainsString('Duplicate code "PAY-1001"', implode("\n", $errors));
    }

    public function testValidatorCatchesBadFormatAndEmptyMessage(): void
    {
        $errors = EventCatalog::validate([MalformedEvent::class]);

        $joined = implode("\n", $errors);
        self::assertStringContainsString('does not match', $joined);
        self::assertStringContainsString('Empty message', $joined);
    }

    public function testAssertValidThrowsOnAnInvalidCatalog(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EventCatalog::assertValid([MalformedEvent::class]);
    }
}

/** A second catalog that collides with {@see SampleEvent} on PAY-1001. */
enum DuplicateEvent: string implements EventCode
{
    case Clash = 'PAY-1001';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return Level::Notice;
    }

    public function message(): string
    {
        return 'Colliding code';
    }
}

/** A malformed catalog: a code that breaks the pattern and an empty message. */
enum MalformedEvent: string implements EventCode
{
    case BadFormat = 'lowercase_and_no_number';
    case Empty     = 'OK-9001';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return Level::Warning;
    }

    public function message(): string
    {
        return $this === self::Empty ? '' : 'has a message';
    }
}
