# The event catalog

Some events deserve a stable name: the ones an alert, a dashboard or a support script searches for. A catalog gives each one a code, a level and a message, declared once.

## `EventCode`

An `EventCode` is a backed enum whose value is the code. Its level and message are derived from the case, so they cannot disagree with it:

```php
use Hallmark\Event\EventCode;
use Hallmark\Formatter\LineFormatter;
use Hallmark\Handler\StreamHandler;
use Hallmark\Level;
use Hallmark\Logger;

enum PaymentEvent: string implements EventCode
{
    case AuthorizationFailed = 'PAY-1001';
    case WebhookReceived     = 'PAY-1101';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return match ($this) {
            self::AuthorizationFailed => Level::Error,
            self::WebhookReceived     => Level::Info,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::AuthorizationFailed => 'Payment authorization failed',
            self::WebhookReceived     => 'Payment webhook received',
        };
    }
}

$handler = new StreamHandler('php://stdout');
$handler->setFormatter(new LineFormatter("%level_name%: %message% %context%\n"));
$log = new Logger('app', [$handler]);

$log->event(PaymentEvent::AuthorizationFailed, ['order' => 4711]);
```

```text
ERROR: Payment authorization failed {"order":4711,"event_code":"PAY-1001"}
```

`Logger::event()` logs at the event's level, with its message, and adds the code to the context as `event_code`, where a log store can search and group by it.

## Validating a catalog

`EventCatalog::validate()` checks every case of the enums it is given and returns what is wrong, one sentence each; `assertValid()` throws an `InvalidArgumentException` listing them. Three rules hold:

- every code matches the pattern, `EventCatalog::DEFAULT_CODE_PATTERN` (`/^[A-Z][A-Z0-9]*-\d{3,}$/`, a domain prefix, a hyphen and three digits or more) unless another is given;
- no code is used twice, across all the enums checked together;
- no message is empty.

```php
use Hallmark\Event\EventCatalog;
use Hallmark\Event\EventCode;
use Hallmark\Level;

enum ShippingEvent: string implements EventCode
{
    case Dispatched = 'SHIP-200';
    case Lost       = 'ship-404';
    case Again      = 'PAY-1001';

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
        return $this === self::Dispatched ? 'Parcel dispatched' : '';
    }
}

enum BillingEvent: string implements EventCode
{
    case Charged = 'PAY-1001';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return Level::Info;
    }

    public function message(): string
    {
        return 'Card charged';
    }
}

foreach (EventCatalog::validate([BillingEvent::class, ShippingEvent::class]) as $problem) {
    echo $problem, "\n";
}
```

```text
Code "ship-404" on ShippingEvent::Lost does not match /^[A-Z][A-Z0-9]*-\d{3,}$/.
Empty message on ShippingEvent::Lost.
Duplicate code "PAY-1001" on ShippingEvent::Again (already defined on BillingEvent::Charged).
Empty message on ShippingEvent::Again.
```

Run `assertValid()` in a test, or at boot, so the catalog stays honest as it grows.
