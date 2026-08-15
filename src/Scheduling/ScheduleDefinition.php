<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use TondbadSwoole\Scheduling\Contracts\Task;
use TondbadSwoole\Scheduling\Contracts\Trigger;
use TondbadSwoole\Scheduling\Triggers\CronTrigger;

class ScheduleDefinition
{
    public ?DateTimeImmutable $nextRunAt = null;
    public ?DateTimeImmutable $lastRunAt = null;
    public ?DateTimeImmutable $lockedUntil = null;
    public ?string $lastRunResult = null;
    public ?string $nodeId = null;
    public ?string $lockedRunKey = null;
    public int $runCount = 0;
    public int $failCount = 0;
    public int $version = 0;
    public string $status = 'active';

    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public Trigger $trigger,
        public Task $task,
        public ?string $description = null,
        public ?DateTimeZone $timezone = null,
        public ?string $betweenStart = null,
        public ?string $betweenEnd = null,
        public bool $unlessBetween = false,
        public ?int $withoutOverlappingLease = null,
        public bool $runInBackground = false,
        public ?string $outputPath = null,
        public int $maxAttempts = 1,
        public array $backoff = [],
        public string $misfire = 'smart',
        public ?int $rateLimitMax = null,
        public ?int $rateLimitWindow = null,
        public ?string $queue = null,
        public ?string $connection = null,
        public array $data = [],
        public ?DateTimeImmutable $startDate = null,
        public ?DateTimeImmutable $endDate = null,
        public array $tags = [],
    ) {
    }

    public static function create(string $id, ?string $name = null): self
    {
        return new self(
            id: $id,
            name: $name ?? $id,
            trigger: CronTrigger::fromExpression('* * * * *'),
            task: new Tasks\ClosureTask('noop', static fn () => null),
        );
    }

    public function withTrigger(Trigger $trigger): self
    {
        $clone = clone $this;
        $clone->trigger = $trigger;

        return $clone;
    }

    public function withTask(Task $task): self
    {
        $clone = clone $this;
        $clone->task = $task;

        return $clone;
    }

    public function withTimezone(string|DateTimeZone $timezone): self
    {
        $clone = clone $this;
        $clone->timezone = is_string($timezone) ? new DateTimeZone($timezone) : $timezone;

        return $clone;
    }

    public function withDescription(string $description): self
    {
        $clone = clone $this;
        $clone->description = $description;

        return $clone;
    }

    public function withBetween(string $start, string $end, bool $unless = false): self
    {
        $clone = clone $this;
        $clone->betweenStart = $start;
        $clone->betweenEnd = $end;
        $clone->unlessBetween = $unless;

        return $clone;
    }

    public function isDue(DateTimeInterface $time): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->startDate !== null && $time < $this->startDate) {
            return false;
        }

        if ($this->endDate !== null && $time > $this->endDate) {
            return false;
        }

        if ($this->lastRunAt !== null) {
            $runKey = $this->trigger->getRunKey($time);
            $lastRunKey = $this->trigger->getRunKey($this->lastRunAt);

            if ($runKey === $lastRunKey) {
                return false;
            }
        }

        $date = DateTimeImmutable::createFromInterface($time);

        if ($this->timezone !== null) {
            $date = $date->setTimezone($this->timezone);
        }

        if ($this->betweenStart !== null && $this->betweenEnd !== null) {
            $timeString = $date->format('H:i');

            if (!$this->inBetweenWindow($timeString, $this->betweenStart, $this->betweenEnd)) {
                return false;
            }
        }

        return $this->trigger->isDue($date);
    }

    public function getNextRunDate(DateTimeInterface $from, bool $allowCurrentDate = true): DateTimeImmutable
    {
        if ($this->nextRunAt !== null && $this->nextRunAt >= $from) {
            return $this->nextRunAt;
        }

        return $this->trigger->getNextRunDate($from, $this->timezone, $allowCurrentDate);
    }

    public function getExpression(): string
    {
        if ($this->trigger instanceof CronTrigger) {
            return $this->trigger->getExpression();
        }

        return '';
    }

    public function getDescription(): string
    {
        return $this->description ?? $this->name;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'trigger' => $this->trigger->toArray(),
            'task' => $this->task->toArray(),
            'timezone' => $this->timezone?->getName(),
            'betweenStart' => $this->betweenStart,
            'betweenEnd' => $this->betweenEnd,
            'unlessBetween' => $this->unlessBetween,
            'withoutOverlappingLease' => $this->withoutOverlappingLease,
            'runInBackground' => $this->runInBackground,
            'outputPath' => $this->outputPath,
            'maxAttempts' => $this->maxAttempts,
            'backoff' => $this->backoff,
            'misfire' => $this->misfire,
            'rateLimitMax' => $this->rateLimitMax,
            'rateLimitWindow' => $this->rateLimitWindow,
            'queue' => $this->queue,
            'connection' => $this->connection,
            'data' => $this->data,
            'startDate' => $this->startDate?->format('Y-m-d H:i:s'),
            'endDate' => $this->endDate?->format('Y-m-d H:i:s'),
            'tags' => $this->tags,
            'nextRunAt' => $this->nextRunAt?->format('Y-m-d H:i:s'),
            'lastRunAt' => $this->lastRunAt?->format('Y-m-d H:i:s'),
            'lastRunResult' => $this->lastRunResult,
            'runCount' => $this->runCount,
            'failCount' => $this->failCount,
            'status' => $this->status,
            'version' => $this->version,
            'lockedUntil' => $this->lockedUntil?->format('Y-m-d H:i:s'),
            'nodeId' => $this->nodeId,
            'lockedRunKey' => $this->lockedRunKey,
        ];
    }

    public static function fromArray(array $data, ScheduleRegistry $registry): self
    {
        $definition = new self(
            id: $data['id'],
            name: $data['name'] ?? $data['id'],
            trigger: Triggers\TriggerFactory::make($data['trigger'] ?? []),
            task: Tasks\TaskFactory::make($data['task'] ?? [], $registry),
        );

        $definition->description = $data['description'] ?? null;
        $definition->timezone = (isset($data['timezone']) && $data['timezone'] !== '' && $data['timezone'] !== null)
            ? new DateTimeZone($data['timezone'])
            : null;
        $definition->betweenStart = $data['betweenStart'] ?? null;
        $definition->betweenEnd = $data['betweenEnd'] ?? null;
        $definition->unlessBetween = $data['unlessBetween'] ?? false;
        $definition->withoutOverlappingLease = $data['withoutOverlappingLease'] ?? null;
        $definition->runInBackground = $data['runInBackground'] ?? false;
        $definition->outputPath = $data['outputPath'] ?? null;
        $definition->maxAttempts = $data['maxAttempts'] ?? 1;
        $definition->backoff = $data['backoff'] ?? [];
        $definition->misfire = $data['misfire'] ?? 'smart';
        $definition->rateLimitMax = $data['rateLimitMax'] ?? null;
        $definition->rateLimitWindow = $data['rateLimitWindow'] ?? null;
        $definition->queue = $data['queue'] ?? null;
        $definition->connection = $data['connection'] ?? null;
        $definition->data = $data['data'] ?? [];
        $definition->startDate = (isset($data['startDate']) && $data['startDate'] !== '' && $data['startDate'] !== null)
            ? new DateTimeImmutable($data['startDate'])
            : null;
        $definition->endDate = (isset($data['endDate']) && $data['endDate'] !== '' && $data['endDate'] !== null)
            ? new DateTimeImmutable($data['endDate'])
            : null;
        $definition->tags = $data['tags'] ?? [];
        $definition->nextRunAt = (isset($data['nextRunAt']) && $data['nextRunAt'] !== '' && $data['nextRunAt'] !== null)
            ? new DateTimeImmutable($data['nextRunAt'])
            : null;
        $definition->lastRunAt = (isset($data['lastRunAt']) && $data['lastRunAt'] !== '' && $data['lastRunAt'] !== null)
            ? new DateTimeImmutable($data['lastRunAt'])
            : null;
        $definition->lastRunResult = $data['lastRunResult'] ?? null;
        $definition->runCount = $data['runCount'] ?? 0;
        $definition->failCount = $data['failCount'] ?? 0;
        $definition->status = $data['status'] ?? 'active';
        $definition->version = $data['version'] ?? 0;
        $definition->lockedUntil = (isset($data['lockedUntil']) && $data['lockedUntil'] !== '' && $data['lockedUntil'] !== null)
            ? new DateTimeImmutable($data['lockedUntil'])
            : null;
        $definition->nodeId = $data['nodeId'] ?? null;
        $definition->lockedRunKey = $data['lockedRunKey'] ?? null;

        return $definition;
    }

    private function inBetweenWindow(string $time, string $start, string $end): bool
    {
        [$startHour, $startMinute] = $this->parseTime($start);
        [$endHour, $endMinute] = $this->parseTime($end);

        $current = (int) str_replace(':', '', $time);
        $startValue = $startHour * 100 + $startMinute;
        $endValue = $endHour * 100 + $endMinute;

        $inWindow = $startValue <= $endValue
            ? ($current >= $startValue && $current <= $endValue)
            : ($current >= $startValue || $current <= $endValue);

        return $this->unlessBetween ? !$inWindow : $inWindow;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function parseTime(string $time): array
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            throw new \InvalidArgumentException("Invalid time format: {$time}. Expected HH:MM.");
        }

        $hour = (int) $matches[1];
        $minute = (int) $matches[2];

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            throw new \InvalidArgumentException("Invalid time value: {$time}.");
        }

        return [$hour, $minute];
    }
}
