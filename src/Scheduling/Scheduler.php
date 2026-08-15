<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Throwable;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Queue\QueueManager;
use TondbadSwoole\Queue\QueueInterface;
use TondbadSwoole\Queue\Jobs\ScheduledJob;
use TondbadSwoole\Scheduling\Contracts\LockProvider;
use TondbadSwoole\Scheduling\Contracts\ScheduleStore;
use TondbadSwoole\Scheduling\Contracts\Task as TaskContract;
use TondbadSwoole\Scheduling\Events\ScheduleEvent;
use TondbadSwoole\Scheduling\Locks\NullLockProvider;
use TondbadSwoole\Scheduling\Tasks\QueueTask;
use TondbadSwoole\Scheduling\Tasks\TaskFactory;
use TondbadSwoole\Queue\RateLimiter\RateLimiterInterface;
use TondbadSwoole\Queue\RateLimiter\NullRateLimiter;

class Scheduler
{
    private NextRunCalculator $calculator;

    public function __construct(
        private readonly ScheduleStore $store,
        private readonly ScheduleRegistry $registry,
        private readonly Container $container,
        private readonly string $basePath,
        private readonly LockProvider $lockProvider = new NullLockProvider(),
        private readonly ?EventDispatcher $dispatcher = null,
        private readonly ?RateLimiterInterface $rateLimiter = null,
    ) {
        $this->calculator = new NextRunCalculator();
    }

    public function store(): ScheduleStore
    {
        return $this->store;
    }

    public function upsert(ScheduleDefinition $definition): void
    {
        $this->store->upsert($definition);

        $this->emit(new ScheduleEvent('created', $definition->getDescription() ?: $definition->id));
    }

    public function remove(string $id): void
    {
        $this->store->delete($id);

        $this->emit(new ScheduleEvent('deleted', $id));
    }

    public function pause(string $id): void
    {
        $this->store->pause($id);

        $this->emit(new ScheduleEvent('paused', $id));
    }

    public function resume(string $id): void
    {
        $this->store->resume($id);

        $this->emit(new ScheduleEvent('resumed', $id));
    }

    public function trigger(string $id, ?DateTimeInterface $time = null): bool
    {
        $definition = $this->store->find($id);

        if ($definition === null) {
            return false;
        }

        return $this->runDefinition($definition, $time ?? new DateTimeImmutable(), skipDue: true);
    }

    public function status(string $id): ?array
    {
        $definition = $this->store->find($id);

        if ($definition === null) {
            return null;
        }

        return [
            'id' => $definition->id,
            'name' => $definition->name,
            'status' => $definition->status,
            'nextRunAt' => $definition->getNextRunDate(new DateTimeImmutable()),
            'lastRunAt' => $definition->lastRunAt,
            'runCount' => $definition->runCount,
            'failCount' => $definition->failCount,
            'nodeId' => $definition->nodeId,
        ];
    }

    /**
     * @return list<ScheduleDefinition>
     */
    public function definitions(): array
    {
        return $this->store->all();
    }

    /**
     * @return list<ScheduleDefinition>
     */
    public function due(DateTimeInterface $time): array
    {
        return array_values(array_filter(
            $this->store->due($time),
            fn (ScheduleDefinition $definition) => $this->isDue($definition, $time),
        ));
    }

    public function isDue(ScheduleDefinition $definition, DateTimeInterface $time): bool
    {
        return $definition->isDue($time);
    }

    public function getNextRunDate(ScheduleDefinition $definition, DateTimeInterface $from): DateTimeImmutable
    {
        return $definition->getNextRunDate($from);
    }

    public function getNextRunDateForAll(DateTimeInterface $from): ?DateTimeImmutable
    {
        $next = null;

        foreach ($this->store->all() as $definition) {
            $candidate = $this->getNextRunDate($definition, $from);

            if ($next === null || $candidate < $next) {
                $next = $candidate;
            }
        }

        return $next;
    }

    public function runDue(DateTimeInterface $time, ?string $nodeId = null): int
    {
        $ran = 0;

        foreach ($this->due($time) as $definition) {
            $runKey = $definition->trigger->getRunKey($time);
            $lease = $definition->withoutOverlappingLease ?? 60;
            $expiresAt = DateTimeImmutable::createFromInterface($time)->modify("+{$lease} seconds");

            if ($nodeId !== null && !$this->store->claim($definition->id, $nodeId, $runKey, $expiresAt)) {
                continue;
            }

            $ranDefinition = $this->runDefinition($definition, $time, skipDue: true, runKey: $runKey, nodeId: $nodeId);

            if ($ranDefinition) {
                $ran++;
            } elseif ($nodeId !== null) {
                $this->store->release($definition->id, $nodeId, $runKey);
            }
        }

        return $ran;
    }

    public function runDefinition(ScheduleDefinition $definition, DateTimeInterface $time, bool $skipDue = false, ?string $runKey = null, ?string $nodeId = null): bool
    {
        if (!$skipDue && !$this->isDue($definition, $time)) {
            return false;
        }

        if ($definition->withoutOverlappingLease !== null) {
            if (!$this->lockProvider->acquire($this->lockKey($definition), 0)) {
                return false;
            }
        }

        $this->emit(new ScheduleEvent('starting', $definition->getDescription() ?: $definition->id));

        $outputPath = $definition->outputPath;
        $ran = false;

        try {
            if ($this->rateLimiter !== null && $definition->rateLimitMax !== null && $definition->rateLimitWindow !== null) {
                $key = 'schedule:' . $definition->id;

                if (!$this->rateLimiter->attempt($key, $definition->rateLimitMax, $definition->rateLimitWindow)) {
                    $this->emit(new ScheduleEvent('skipped', $definition->getDescription() ?: $definition->id, ['reason' => 'rate_limit']));

                    return false;
                }
            }

            if ($nodeId !== null && !$definition->task instanceof QueueTask && !$definition->runInBackground) {
                $this->dispatchScheduledJob($definition, $runKey ?? 'manual');
            } else {
                $this->executeTask($definition->task, $outputPath);
            }

            $definition->lastRunAt = DateTimeImmutable::createFromInterface($time);
            $definition->runCount++;
            $definition->lastRunResult = 'success';
            $definition->failCount = 0;
            $definition->nextRunAt = $this->calculator->calculate($definition, $time);

            $this->store->upsert($definition);

            $this->emit(new ScheduleEvent('ran', $definition->getDescription() ?: $definition->id));

            $ran = true;
        } catch (Throwable $e) {
            $definition->failCount++;
            $definition->lastRunResult = $e->getMessage();

            if ($definition->failCount >= $definition->maxAttempts) {
                $definition->status = 'failed';
            }

            $definition->nextRunAt = $this->calculator->calculate($definition, $time);
            $this->store->upsert($definition);

            $this->emit(new ScheduleEvent('failed', $definition->getDescription() ?: $definition->id, [
                'error' => $e->getMessage(),
            ]));
        } finally {
            if ($definition->withoutOverlappingLease !== null) {
                $this->lockProvider->release($this->lockKey($definition));
            }
        }

        return $ran;
    }

    public function recoverLocks(DateTimeInterface $before, ?string $nodeId = null): int
    {
        $recovered = 0;

        foreach ($this->store->all() as $definition) {
            if ($definition->lockedUntil === null || $definition->lockedUntil >= $before) {
                continue;
            }

            $runKey = $definition->lockedRunKey ?? 'unknown';
            $lockOwner = $definition->nodeId ?? 'unknown';

            if ($nodeId !== null && $lockOwner !== $nodeId) {
                continue;
            }

            $this->store->release($definition->id, $lockOwner, $runKey);

            if ($definition->withoutOverlappingLease !== null) {
                $this->lockProvider->release($this->lockKey($definition));
            }

            $definition->lockedUntil = null;
            $definition->nodeId = null;
            $definition->lockedRunKey = null;
            $this->store->upsert($definition);

            $recovered++;
        }

        return $recovered;
    }

    public function warmNextRunDates(DateTimeInterface $time): void
    {
        foreach ($this->store->all() as $definition) {
            if ($definition->nextRunAt !== null) {
                continue;
            }

            $definition->nextRunAt = $this->calculator->calculate($definition, $time);
            $this->store->upsert($definition);
        }
    }

    private function executeTask(TaskContract $task, ?string $outputPath): mixed
    {
        $runner = new TaskRunner($this->container, $this->basePath, $this->registry);

        return $runner->run($task, $outputPath);
    }

    private function dispatchScheduledJob(ScheduleDefinition $definition, string $runKey): void
    {
        $queue = $this->container->make(QueueManager::class)->connection($definition->connection);
        $job = new ScheduledJob($definition->task->toArray(), $definition->outputPath, $definition->id, $runKey);

        if ($definition->queue !== null) {
            $job->onQueue($definition->queue);
        }

        if ($definition->maxAttempts > 1) {
            $job->tries($definition->maxAttempts);

            if ($definition->backoff !== []) {
                $job->backoff($definition->backoff);
            }
        }

        $queue->push($job, $definition->queue);
    }

    private function lockKey(ScheduleDefinition $definition): string
    {
        return $definition->description ?? $definition->id;
    }

    private function emit(ScheduleEvent $event): void
    {
        if ($this->dispatcher === null) {
            return;
        }

        if ($this->dispatcher->hasListeners($event) || $this->dispatcher->hasListeners($event->name())) {
            $this->dispatcher->dispatch($event);
        }
    }
}
