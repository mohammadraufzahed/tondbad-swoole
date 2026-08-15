<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Triggers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TondbadSwoole\Scheduling\Contracts\Trigger;

class DelayTrigger implements Trigger
{
    private readonly DateTimeImmutable $runAt;

    public function __construct(
        DateTimeImmutable|int $runAt = 0,
    ) {
        $this->runAt = $runAt instanceof DateTimeImmutable
            ? $runAt
            : new DateTimeImmutable("+{$runAt} seconds");
    }

    public static function until(DateTimeImmutable $runAt): self
    {
        return new self($runAt);
    }

    public function isDue(DateTimeInterface $time): bool
    {
        return $time >= $this->runAt;
    }

    public function getNextRunDate(DateTimeInterface $from, ?DateTimeZone $tz = null, bool $allowCurrentDate = false): DateTimeImmutable
    {
        $next = $this->runAt;

        if ($next < $from) {
            $next = new DateTimeImmutable('2100-01-01 00:00:00');
        }

        if ($tz !== null) {
            $next = $next->setTimezone($tz);
        }

        return $next;
    }

    public function getRunKey(DateTimeInterface $time): string
    {
        return 'delay';
    }

    public function toArray(): array
    {
        return [
            'type' => 'delay',
            'runAt' => $this->runAt->format('Y-m-d H:i:s'),
        ];
    }
}
