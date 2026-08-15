<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Triggers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use TondbadSwoole\Scheduling\Contracts\Trigger;

class IntervalTrigger implements Trigger
{
    public function __construct(
        private readonly int $seconds,
        private readonly ?DateTimeImmutable $startAt = null,
    ) {
        if ($this->seconds <= 0) {
            throw new InvalidArgumentException('Interval must be greater than 0 seconds.');
        }
    }

    public static function fromSeconds(int $seconds, ?DateTimeImmutable $startAt = null): self
    {
        return new self($seconds, $startAt);
    }

    public function isDue(DateTimeInterface $time): bool
    {
        $next = $this->getNextRunDate($time);

        return $next->getTimestamp() === (int) $time->getTimestamp();
    }

    public function getNextRunDate(DateTimeInterface $from, ?DateTimeZone $tz = null, bool $allowCurrentDate = false): DateTimeImmutable
    {
        $start = $this->startAt ?? $from;
        $startTs = $start->getTimestamp();
        $fromTs = $from->getTimestamp();

        if ($fromTs <= $startTs) {
            $nextTs = $startTs;
        } else {
            $elapsed = $fromTs - $startTs;
            $multiplier = (int) ceil($elapsed / $this->seconds);
            $nextTs = $startTs + ($multiplier * $this->seconds);
        }

        $next = new DateTimeImmutable("@{$nextTs}");

        if ($tz !== null) {
            $next = $next->setTimezone($tz);
        } else {
            $next = $next->setTimezone($from->getTimezone() ?: new DateTimeZone('UTC'));
        }

        return $next;
    }

    public function getRunKey(DateTimeInterface $time): string
    {
        return (string) (int) floor($time->getTimestamp() / $this->seconds);
    }

    public function toArray(): array
    {
        return [
            'type' => 'interval',
            'seconds' => $this->seconds,
            'startAt' => $this->startAt?->format('Y-m-d H:i:s'),
        ];
    }
}
