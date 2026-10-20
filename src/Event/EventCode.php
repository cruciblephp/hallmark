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

namespace Hallmark\Event;

use Hallmark\Level;

/**
 * A stable, catalogued log event: a machine identifier paired with its severity
 * and a human message.
 *
 * The intended carrier is a backed enum — one per domain — whose backing value
 * IS the code, so the identifier is declared exactly once and `level()` /
 * `message()` are derived from the case via `match`. That kills the classic
 * catalog smell where a code is repeated across a constant name and its string
 * value, and where severity lives only in a naming convention nothing enforces.
 *
 *   enum PaymentEvent: string implements EventCode
 *   {
 *       case AuthorizationFailed = 'PAY-1001';
 *       case WebhookReceived     = 'PAY-1101';
 *
 *       public function code(): string { return $this->value; }
 *
 *       public function level(): Level
 *       {
 *           return match ($this) {
 *               self::AuthorizationFailed => Level::Error,
 *               self::WebhookReceived     => Level::Info,
 *           };
 *       }
 *
 *       public function message(): string
 *       {
 *           return match ($this) {
 *               self::AuthorizationFailed => 'Payment authorization failed',
 *               self::WebhookReceived     => 'Payment webhook received',
 *           };
 *       }
 *   }
 *
 * Log it with {@see \Hallmark\Logger::event()}; validate the whole
 * catalog with {@see EventCatalog}.
 */
interface EventCode
{
    /** The stable machine identifier, e.g. `PAY-1001`. Declared once. */
    public function code(): string;

    /** The severity to log this event at — derived, so it cannot drift. */
    public function level(): Level;

    /** The human-readable message template. */
    public function message(): string;
}
