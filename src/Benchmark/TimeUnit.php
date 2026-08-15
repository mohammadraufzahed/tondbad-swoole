<?php

declare(strict_types=1);

namespace TondbadSwoole\Benchmark;

enum TimeUnit: string
{
    case Nanoseconds = 'ns';
    case Microseconds = 'us';
    case Milliseconds = 'ms';
    case Seconds = 's';

    public static function fromString(string $value): self
    {
        return match (strtolower($value)) {
            'ns', 'nanoseconds', 'nanos' => self::Nanoseconds,
            'us', 'microseconds', 'micros' => self::Microseconds,
            'ms', 'milliseconds', 'millis' => self::Milliseconds,
            's', 'seconds', 'sec' => self::Seconds,
            default => self::Microseconds,
        };
    }

    public function toNanos(float $value): float
    {
        return match ($this) {
            self::Nanoseconds => $value,
            self::Microseconds => $value * 1_000.0,
            self::Milliseconds => $value * 1_000_000.0,
            self::Seconds => $value * 1_000_000_000.0,
        };
    }

    public function fromNanos(float $nanos): float
    {
        return match ($this) {
            self::Nanoseconds => $nanos,
            self::Microseconds => $nanos / 1_000.0,
            self::Milliseconds => $nanos / 1_000_000.0,
            self::Seconds => $nanos / 1_000_000_000.0,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Nanoseconds => 'ns',
            self::Microseconds => 'μs',
            self::Milliseconds => 'ms',
            self::Seconds => 's',
        };
    }
}
