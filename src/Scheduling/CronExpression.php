<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use Cron\CronExpression as BaseCronExpression;
use DateTimeImmutable;
use DateTimeInterface;

class CronExpression
{
    private BaseCronExpression $cron;

    public function __construct(string $expression)
    {
        $this->cron = new BaseCronExpression($expression);
    }

    public function getExpression(): string
    {
        return $this->cron->getExpression();
    }

    public function isDue(DateTimeInterface $time): bool
    {
        return $this->cron->isDue($time);
    }

    public function getNextRunDate(DateTimeInterface $from): DateTimeImmutable
    {
        return DateTimeImmutable::createFromMutable($this->cron->getNextRunDate($from));
    }
}
