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

namespace Hallmark;

use function strtolower;
use function strtoupper;

use Hallmark\Exception\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * The eight RFC 5424 severities, as a backed enum.
 *
 * The integer backing preserves ordering — Debug is the lowest severity,
 * Emergency the highest — so a handler can threshold with a plain `>=` on the
 * value, while the enum gives exhaustive `match` and identity comparison that a
 * loose set of integer constants never could. Values match the de-facto Monolog
 * scale so existing log tooling keeps working.
 */
enum Level: int
{
    case Debug     = 100;
    case Info      = 200;
    case Notice    = 250;
    case Warning   = 300;
    case Error     = 400;
    case Critical  = 500;
    case Alert     = 550;
    case Emergency = 600;

    /**
     * Resolve a PSR-3 {@see LogLevel} string (or an already-resolved Level) to a
     * case. This is the single funnel every public `log()`-style call passes
     * through, so an invalid level fails loudly and in one place.
     */
    public static function fromPsr(string|self $level): self
    {
        if ($level instanceof self) {
            return $level;
        }

        return match (strtolower($level)) {
            LogLevel::DEBUG     => self::Debug,
            LogLevel::INFO      => self::Info,
            LogLevel::NOTICE    => self::Notice,
            LogLevel::WARNING   => self::Warning,
            LogLevel::ERROR     => self::Error,
            LogLevel::CRITICAL  => self::Critical,
            LogLevel::ALERT     => self::Alert,
            LogLevel::EMERGENCY => self::Emergency,
            default             => throw new InvalidArgumentException('Unknown PSR-3 log level: ' . $level),
        };
    }

    /** Resolve an uppercase RFC 5424 name (`WARNING`, `DEBUG`, …) to a case. */
    public static function fromName(string $name): self
    {
        return match (strtoupper($name)) {
            'DEBUG'     => self::Debug,
            'INFO'      => self::Info,
            'NOTICE'    => self::Notice,
            'WARNING'   => self::Warning,
            'ERROR'     => self::Error,
            'CRITICAL'  => self::Critical,
            'ALERT'     => self::Alert,
            'EMERGENCY' => self::Emergency,
            default     => throw new InvalidArgumentException('Unknown log level name: ' . $name),
        };
    }

    /** Uppercase RFC 5424 name used on the wire: `DEBUG`, `WARNING`, `EMERGENCY`. */
    public function getName(): string
    {
        return match ($this) {
            self::Debug     => 'DEBUG',
            self::Info      => 'INFO',
            self::Notice    => 'NOTICE',
            self::Warning   => 'WARNING',
            self::Error     => 'ERROR',
            self::Critical  => 'CRITICAL',
            self::Alert     => 'ALERT',
            self::Emergency => 'EMERGENCY',
        };
    }

    /** The PSR-3 {@see LogLevel} constant string for this severity. */
    public function toPsr(): string
    {
        return match ($this) {
            self::Debug     => LogLevel::DEBUG,
            self::Info      => LogLevel::INFO,
            self::Notice    => LogLevel::NOTICE,
            self::Warning   => LogLevel::WARNING,
            self::Error     => LogLevel::ERROR,
            self::Critical  => LogLevel::CRITICAL,
            self::Alert     => LogLevel::ALERT,
            self::Emergency => LogLevel::EMERGENCY,
        };
    }

    /** RFC 5424 numeric severity (Emergency 0 … Debug 7), as syslog expects it. */
    public function toRfc5424(): int
    {
        return match ($this) {
            self::Emergency => 0,
            self::Alert     => 1,
            self::Critical  => 2,
            self::Error     => 3,
            self::Warning   => 4,
            self::Notice    => 5,
            self::Info      => 6,
            self::Debug     => 7,
        };
    }

    /** Whether a handler thresholded at `$this` should act on a record of `$level`. */
    public function includes(self $level): bool
    {
        return $level->value >= $this->value;
    }

    public function isHigherThan(self $level): bool
    {
        return $this->value > $level->value;
    }

    public function isLowerThan(self $level): bool
    {
        return $this->value < $level->value;
    }
}
