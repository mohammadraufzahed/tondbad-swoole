<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Triggers;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TondbadSwoole\Scheduling\Contracts\Trigger;

class OnceTrigger implements Trigger
{
    public function __construct(
        private readonly DateTimeImmutable $at = new DateTimeImmutable(),
    ) {
    }

    public static function at(DateTimeImmutable $at): self
    {
        return new self($at);
    }

    public function isDue(DateTimeInterface $time): bool
    {
        return $time >= $this->at;
    }

    public function getNextRunDate(DateTimeInterface $from, ?DateTimeZone $tz = null, bool $allowCurrentDate = false): DateTimeImmutable
    {
        $next = $this->at;

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
        return 'once';
    }

    public function toArray(): array
    {
        return [
            'type' => 'once',
            'at' => $this->at->format('Y-m-d H:i:s'),
        ];
    }
}
