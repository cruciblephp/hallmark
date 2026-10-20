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

namespace Hallmark\Tests\Fixtures;

use Hallmark\Event\EventCode;
use Hallmark\Level;

/**
 * A well-formed example catalog: the code is the backing value (declared once),
 * severity and message are derived. Illustrates the pattern; not a real taxonomy.
 */
enum SampleEvent: string implements EventCode
{
    case AuthorizationFailed = 'PAY-1001';
    case WebhookReceived     = 'PAY-1101';
    case CartValidationFailed = 'ORD-2001';

    public function code(): string
    {
        return $this->value;
    }

    public function level(): Level
    {
        return match ($this) {
            self::AuthorizationFailed  => Level::Error,
            self::WebhookReceived      => Level::Info,
            self::CartValidationFailed => Level::Warning,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::AuthorizationFailed  => 'Payment authorization failed',
            self::WebhookReceived      => 'Payment webhook received',
            self::CartValidationFailed => 'Cart validation failed',
        };
    }
}
