<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Stores;

use DateTimeImmutable;
use DateTimeInterface;
use TondbadSwoole\Scheduling\Contracts\ScheduleStore;
use TondbadSwoole\Scheduling\ScheduleDefinition;

class MemoryScheduleStore implements ScheduleStore
{
    /**
     * @var array<string, ScheduleDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<string, array{nodeId: string, expiresAt: int}>
     */
    private array $locks = [];

    public function all(): array
    {
        return array_values($this->definitions);
    }

    public function find(string $id): ?ScheduleDefinition
    {
        return $this->definitions[$id] ?? null;
    }

    public function upsert(ScheduleDefinition $definition): void
    {
        $this->definitions[$definition->id] = $definition;
    }

    public function delete(string $id): void
    {
        unset($this->definitions[$id]);
    }

    public function pause(string $id): void
    {
        if (isset($this->definitions[$id])) {
            $this->definitions[$id]->status = 'paused';
        }
    }

    public function resume(string $id): void
    {
        if (isset($this->definitions[$id])) {
            $this->definitions[$id]->status = 'active';
        }
    }

    public function due(DateTimeInterface $before): array
    {
        return array_values(array_filter(
            $this->definitions,
            static fn (ScheduleDefinition $definition) => $definition->status === 'active',
        ));
    }

    public function claim(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool
    {
        $key = "{$id}:{$runKey}";
        $expiresTs = $expiresAt->getTimestamp();
        $now = time();

        if (isset($this->locks[$key]) && $this->locks[$key]['expiresAt'] > $now) {
            return false;
        }

        $this->locks[$key] = [
            'nodeId' => $nodeId,
            'expiresAt' => $expiresTs,
        ];

        if (isset($this->definitions[$id])) {
            $this->definitions[$id]->lockedUntil = DateTimeImmutable::createFromInterface($expiresAt);
            $this->definitions[$id]->nodeId = $nodeId;
            $this->definitions[$id]->lockedRunKey = $runKey;
        }

        return true;
    }

    public function release(string $id, string $nodeId, string $runKey): void
    {
        $key = "{$id}:{$runKey}";

        if (isset($this->locks[$key]) && $this->locks[$key]['nodeId'] === $nodeId) {
            unset($this->locks[$key]);
        }

        if (isset($this->definitions[$id]) && $this->definitions[$id]->lockedRunKey === $runKey) {
            $this->definitions[$id]->lockedUntil = null;
            $this->definitions[$id]->nodeId = null;
            $this->definitions[$id]->lockedRunKey = null;
        }
    }

    public function heartbeat(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool
    {
        $key = "{$id}:{$runKey}";

        if (!isset($this->locks[$key]) || $this->locks[$key]['nodeId'] !== $nodeId) {
            return false;
        }

        $this->locks[$key]['expiresAt'] = $expiresAt->getTimestamp();

        if (isset($this->definitions[$id])) {
            $this->definitions[$id]->lockedUntil = DateTimeImmutable::createFromInterface($expiresAt);
        }

        return true;
    }
}
