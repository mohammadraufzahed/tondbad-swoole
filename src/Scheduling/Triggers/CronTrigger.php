<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Triggers;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TondbadSwoole\Scheduling\Contracts\Trigger;

class CronTrigger implements Trigger
{
    private CronExpression $cron;

    public function __construct(string $expression)
    {
        $this->cron = new CronExpression($expression);
    }

    public static function fromExpression(string $expression): self
    {
        return new self($expression);
    }

    public function isDue(DateTimeInterface $time): bool
    {
        return $this->cron->isDue($time);
    }

    public function getNextRunDate(DateTimeInterface $from, ?DateTimeZone $tz = null, bool $allowCurrentDate = false): DateTimeImmutable
    {
        $next = $this->cron->getNextRunDate($from, 0, $allowCurrentDate, $tz?->getName());

        if ($tz !== null) {
            $next->setTimezone($tz);
        }

        return DateTimeImmutable::createFromMutable($next);
    }

    public function getRunKey(DateTimeInterface $time): string
    {
        return (new DateTimeImmutable('@' . $time->getTimestamp()))->format('YmdHi');
    }

    public function getExpression(): string
    {
        return $this->cron->getExpression();
    }

    public function toArray(): array
    {
        return [
            'type' => 'cron',
            'expression' => $this->getExpression(),
        ];
    }
}
