<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Contracts;

use DateTimeInterface;
use TondbadSwoole\Scheduling\ScheduleDefinition;

interface ScheduleStore
{
    /**
     * @return list<ScheduleDefinition>
     */
    public function all(): array;

    public function find(string $id): ?ScheduleDefinition;

    public function upsert(ScheduleDefinition $definition): void;

    public function delete(string $id): void;

    public function pause(string $id): void;

    public function resume(string $id): void;

    /**
     * @return list<ScheduleDefinition>
     */
    public function due(DateTimeInterface $before): array;

    /**
     * Claim a scheduled run occurrence.
     *
     * @param string $id Schedule id.
     * @param string $nodeId Worker node id.
     * @param string $runKey Trigger-specific run occurrence key.
     */
    public function claim(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool;

    public function release(string $id, string $nodeId, string $runKey): void;

    public function heartbeat(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool;
}
