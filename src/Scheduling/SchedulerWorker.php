<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use DateTimeImmutable;
use DateTimeInterface;
use TondbadSwoole\Events\Contracts\EventDispatcher;
use TondbadSwoole\Scheduling\Events\ScheduleEvent;

class SchedulerWorker
{
    private readonly string $nodeId;

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly ?EventDispatcher $dispatcher = null,
        ?string $nodeId = null,
    ) {
        $this->nodeId = $nodeId ?? $this->generateNodeId();
    }

    public function getNodeId(): string
    {
        return $this->nodeId;
    }

    public function run(?DateTimeInterface $startTime = null, bool $once = false, int $sleepSeconds = 60, ?int $maxRuns = null): int
    {
        $totalRuns = 0;

        do {
            $now = $startTime === null ? new DateTimeImmutable() : DateTimeImmutable::createFromInterface($startTime)->setTimestamp(time());
            $this->scheduler->warmNextRunDates($now);
            $this->scheduler->recoverLocks($now, $this->nodeId);

            $runs = $this->tick($now);
            $totalRuns += $runs;

            if ($once) {
                break;
            }

            if ($maxRuns !== null && $totalRuns >= $maxRuns) {
                break;
            }

            $next = $this->scheduler->getNextRunDateForAll($now);
            $sleepUntil = $sleepSeconds;

            if ($next !== null) {
                $seconds = $next->getTimestamp() - $now->getTimestamp();
                $sleepUntil = max(1, min($sleepSeconds, $seconds));
            }

            $this->sleep($sleepUntil);
        } while (true);

        return $totalRuns;
    }

    public function tick(DateTimeInterface $now): int
    {
        $this->emit(new ScheduleEvent('tick', null, ['nodeId' => $this->nodeId]));

        return $this->scheduler->runDue($now, $this->nodeId);
    }

    public function heartbeat(string $scheduleId, string $runKey, DateTimeInterface $expiresAt): bool
    {
        return $this->scheduler->store()->heartbeat($scheduleId, $this->nodeId, $runKey, $expiresAt);
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

    private function sleep(int $seconds): void
    {
        if (class_exists(\OpenSwoole\Coroutine\System::class) && \OpenSwoole\Coroutine\getCid() >= 0) {
            \OpenSwoole\Coroutine\System::sleep($seconds);

            return;
        }

        sleep($seconds);
    }

    private function generateNodeId(): string
    {
        return sprintf('%s-%d-%s', gethostname() ?: 'unknown', getmypid(), bin2hex(random_bytes(4)));
    }
}
