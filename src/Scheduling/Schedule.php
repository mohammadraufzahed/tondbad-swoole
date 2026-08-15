<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Queue\Jobs\Job;
use TondbadSwoole\Scheduling\Tasks\ClosureTask;
use TondbadSwoole\Scheduling\Tasks\CommandTask;
use TondbadSwoole\Scheduling\Tasks\CallableTask;
use TondbadSwoole\Scheduling\Tasks\ExecTask;
use TondbadSwoole\Scheduling\Tasks\QueueTask;

class Schedule
{
    private int $closureCounter = 0;

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly Container $container,
        private readonly string $basePath,
        private readonly ScheduleRegistry $registry,
    ) {
    }

    public function command(string $command, array $parameters = []): Event
    {
        $definition = ScheduleDefinition::create($command)
            ->withTask(new CommandTask($command, $parameters));

        return $this->addEvent($this->makeEvent($definition));
    }

    public function call(callable $callback): Event
    {
        if (is_array($callback) && is_string($callback[0])) {
            $task = new CallableTask($callback);
            $name = is_string($callback[0]) ? $callback[0] . '::' . $callback[1] : 'callable';
        } elseif (is_array($callback) && is_object($callback[0])) {
            $task = new CallableTask([get_class($callback[0]), $callback[1]]);
            $name = get_class($callback[0]) . '@' . $callback[1];
        } elseif (is_string($callback) && str_contains($callback, '::')) {
            $task = new CallableTask($callback);
            $name = $callback;
        } elseif (is_string($callback) && str_contains($callback, '@')) {
            $task = new CallableTask($callback);
            $name = $callback;
        } else {
            if (!$callback instanceof Closure) {
                $callback = Closure::fromCallable($callback);
            }

            $closureId = 'closure-' . ++$this->closureCounter;
            $task = ClosureTask::fromClosure($callback, $this->registry, $closureId);
            $name = $closureId;
        }

        $definition = ScheduleDefinition::create($name)->withTask($task);

        return $this->addEvent($this->makeEvent($definition));
    }

    public function job(Job $job, ?string $queue = null): Event
    {
        $definition = ScheduleDefinition::create($job::class)
            ->withTask(QueueTask::fromJob($job, $queue));

        if ($queue !== null) {
            $definition->queue = $queue;
        }

        return $this->addEvent($this->makeEvent($definition));
    }

    public function exec(string $command, array $parameters = []): Event
    {
        $definition = ScheduleDefinition::create($command)
            ->withTask(new ExecTask($command, $parameters));

        return $this->addEvent($this->makeEvent($definition));
    }

    public function addEvent(Event $event): Event
    {
        $definition = $event->getDefinition();
        $existing = $this->scheduler->store()->find($definition->id);

        if ($existing !== null) {
            $definition->status = $existing->status;
            $definition->nextRunAt = $existing->nextRunAt;
            $definition->lastRunAt = $existing->lastRunAt;
            $definition->lastRunResult = $existing->lastRunResult;
            $definition->runCount = $existing->runCount;
            $definition->failCount = $existing->failCount;
            $definition->nodeId = $existing->nodeId;
            $definition->lockedUntil = $existing->lockedUntil;
            $definition->lockedRunKey = $existing->lockedRunKey;
        }

        $this->scheduler->upsert($definition);

        return $event;
    }

    /**
     * @return list<Event>
     */
    public function events(): array
    {
        return array_map(
            fn (ScheduleDefinition $definition) => $this->makeEvent($definition),
            $this->scheduler->definitions(),
        );
    }

    /**
     * @return list<Event>
     */
    public function dueEvents(DateTimeInterface $time): array
    {
        return array_values(array_filter(
            $this->events(),
            fn (Event $event) => $event->isDue($time),
        ));
    }

    public function runDueEvents(DateTimeInterface $time): int
    {
        return $this->scheduler->runDue($time);
    }

    public function getNextRunDate(DateTimeInterface $from): ?DateTimeImmutable
    {
        return $this->scheduler->getNextRunDateForAll($from);
    }

    private function makeEvent(ScheduleDefinition $definition): Event
    {
        return new Event($definition, $this->scheduler, $this->container, $this->basePath, $this->registry);
    }
}
