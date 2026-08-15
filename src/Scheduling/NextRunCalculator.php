<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use TondbadSwoole\Scheduling\Triggers\CronTrigger;
use TondbadSwoole\Scheduling\Triggers\IntervalTrigger;

class NextRunCalculator
{
    public function calculate(ScheduleDefinition $definition, DateTimeInterface $from, bool $allowCurrentDate = false): DateTimeImmutable
    {
        $next = $definition->trigger->getNextRunDate($from, $definition->timezone, $allowCurrentDate);

        if ($definition->startDate !== null && $next < $definition->startDate) {
            $next = $definition->trigger->getNextRunDate($definition->startDate, $definition->timezone, true);
        }

        return $next;
    }

    /**
     * Determine if a misfired schedule should fire and what next run to record.
     */
    public function handleMisfire(ScheduleDefinition $definition, DateTimeInterface $now, DateTimeInterface $dueAt): array
    {
        $policy = MisfirePolicy::fromString($definition->misfire);

        if ($policy === MisfirePolicy::SMART) {
            $policy = $this->smartPolicy($definition);
        }

        if ($policy === MisfirePolicy::IGNORE) {
            return [
                'fire' => false,
                'nextRunAt' => $this->calculate($definition, $now),
            ];
        }

        if ($policy === MisfirePolicy::FIRE_AND_PROCEED) {
            return [
                'fire' => true,
                'nextRunAt' => $this->calculate($definition, $dueAt),
            ];
        }

        return [
            'fire' => true,
            'nextRunAt' => $this->calculate($definition, $now),
        ];
    }

    private function smartPolicy(ScheduleDefinition $definition): MisfirePolicy
    {
        if ($definition->trigger instanceof IntervalTrigger) {
            return $definition->trigger->toArray()['seconds'] <= 60
                ? MisfirePolicy::IGNORE
                : MisfirePolicy::FIRE_ONCE;
        }

        return MisfirePolicy::FIRE_ONCE;
    }
}
