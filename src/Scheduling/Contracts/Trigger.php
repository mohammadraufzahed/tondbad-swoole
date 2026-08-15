<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Contracts;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

interface Trigger
{
    public function isDue(DateTimeInterface $time): bool;

    public function getNextRunDate(DateTimeInterface $from, ?DateTimeZone $tz = null): DateTimeImmutable;

    /**
     * A stable key for this trigger occurrence at the given time.
     */
    public function getRunKey(DateTimeInterface $time): string;

    public function toArray(): array;
}
