<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Stores;

use DateTimeImmutable;
use DateTimeInterface;
use TondbadSwoole\Database\DatabaseManager;
use TondbadSwoole\Scheduling\Contracts\ScheduleStore;
use TondbadSwoole\Scheduling\ScheduleDefinition;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class DatabaseScheduleStore implements ScheduleStore
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ScheduleRegistry $registry,
        private readonly string $table = 'scheduled_jobs',
    ) {
    }

    public function all(): array
    {
        $rows = $this->database->table($this->table)->get();

        return array_map(fn (array $row) => $this->hydrate($row), $rows);
    }

    public function find(string $id): ?ScheduleDefinition
    {
        $row = $this->database->table($this->table)->where('id', $id)->first();

        if ($row === null) {
            return null;
        }

        return $this->hydrate((array) $row);
    }

    public function upsert(ScheduleDefinition $definition): void
    {
        $data = $definition->toArray();
        unset($data['id'], $data['name']);

        $data['trigger_config'] = json_encode($data['trigger']);
        $data['job_config'] = json_encode($data['task']);
        $data['tags'] = json_encode($data['tags']);
        $data['data'] = json_encode($data['data']);
        $data['backoff'] = json_encode($data['backoff']);
        $data['next_run_at'] = $data['nextRunAt'];
        $data['last_run_at'] = $data['lastRunAt'];
        $data['last_run_result'] = $data['lastRunResult'];
        $data['run_count'] = $data['runCount'];
        $data['fail_count'] = $data['failCount'];
        $data['without_overlapping_lease'] = $data['withoutOverlappingLease'];
        $data['rate_limit_max'] = $data['rateLimitMax'];
        $data['rate_limit_window'] = $data['rateLimitWindow'];
        $data['run_in_background'] = $data['runInBackground'];
        $data['output_path'] = $data['outputPath'];
        $data['max_attempts'] = $data['maxAttempts'];
        $data['start_date'] = $data['startDate'];
        $data['end_date'] = $data['endDate'];
        $data['locked_until'] = $data['lockedUntil'];
        $data['node_id'] = $data['nodeId'];
        $data['locked_run_key'] = $data['lockedRunKey'];
        $data['misfire_policy'] = $data['misfire'];
        $data['between_start'] = $data['betweenStart'];
        $data['between_end'] = $data['betweenEnd'];
        $data['unless_between'] = $data['unlessBetween'];
        $data['queue'] = $data['queue'];
        $data['connection'] = $data['connection'];
        $data['created_at'] = $data['createdAt'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $data['updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        unset(
            $data['trigger'],
            $data['task'],
            $data['nextRunAt'],
            $data['lastRunAt'],
            $data['lastRunResult'],
            $data['runCount'],
            $data['failCount'],
            $data['withoutOverlappingLease'],
            $data['rateLimitMax'],
            $data['rateLimitWindow'],
            $data['runInBackground'],
            $data['outputPath'],
            $data['maxAttempts'],
            $data['startDate'],
            $data['endDate'],
            $data['lockedUntil'],
            $data['nodeId'],
            $data['lockedRunKey'],
            $data['misfire'],
            $data['betweenStart'],
            $data['betweenEnd'],
            $data['unlessBetween'],
            $data['createdAt'],
        );

        $exists = $this->database->table($this->table)->where('id', $definition->id)->exists();

        if ($exists) {
            unset($data['created_at']);
            $this->database->table($this->table)->where('id', $definition->id)->update($data);
        } else {
            $data['id'] = $definition->id;
            $data['name'] = $definition->name;
            $this->database->table($this->table)->insert($data);
        }
    }

    public function delete(string $id): void
    {
        $this->database->table($this->table)->where('id', $id)->delete();
    }

    public function pause(string $id): void
    {
        $this->database->table($this->table)->where('id', $id)->update(['status' => 'paused']);
    }

    public function resume(string $id): void
    {
        $this->database->table($this->table)->where('id', $id)->update(['status' => 'active']);
    }

    public function due(DateTimeInterface $before): array
    {
        $nowString = $before->format('Y-m-d H:i:s');

        $rows = $this->database->table($this->table)
            ->where('status', 'active')
            ->where(function ($query) use ($nowString): void {
                $query->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', $nowString)
                    ->orWhere('locked_until', '<', $nowString);
            })
            ->get();

        return array_map(fn (array $row) => $this->hydrate($row), $rows);
    }

    public function claim(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool
    {
        $expires = $expiresAt->format('Y-m-d H:i:s');
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $updated = $this->database->table($this->table)
            ->where('id', $id)
            ->where(function ($query) use ($now): void {
                $query->whereNull('locked_until')
                    ->orWhere('locked_until', '<', $now);
            })
            ->update([
                'locked_until' => $expires,
                'node_id' => $nodeId,
                'locked_run_key' => $runKey,
            ]);

        return $updated > 0;
    }

    public function release(string $id, string $nodeId, string $runKey): void
    {
        $this->database->table($this->table)
            ->where('id', $id)
            ->where('node_id', $nodeId)
            ->where('locked_run_key', $runKey)
            ->update([
                'locked_until' => null,
                'node_id' => null,
                'locked_run_key' => null,
            ]);
    }

    public function heartbeat(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool
    {
        $updated = $this->database->table($this->table)
            ->where('id', $id)
            ->where('node_id', $nodeId)
            ->where('locked_run_key', $runKey)
            ->update(['locked_until' => $expiresAt->format('Y-m-d H:i:s')]);

        return $updated > 0;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): ScheduleDefinition
    {
        $data = [
            'id' => $row['id'],
            'name' => $row['name'] ?? $row['id'],
            'description' => $row['description'] ?? null,
            'trigger' => json_decode($row['trigger_config'] ?? '{}', true),
            'task' => json_decode($row['job_config'] ?? '{}', true),
            'timezone' => $row['timezone'] ?? null,
            'betweenStart' => $row['between_start'] ?? null,
            'betweenEnd' => $row['between_end'] ?? null,
            'unlessBetween' => (bool) ($row['unless_between'] ?? false),
            'withoutOverlappingLease' => isset($row['without_overlapping_lease']) ? (int) $row['without_overlapping_lease'] : null,
            'runInBackground' => (bool) ($row['run_in_background'] ?? false),
            'outputPath' => $row['output_path'] ?? null,
            'maxAttempts' => (int) ($row['max_attempts'] ?? 1),
            'backoff' => json_decode($row['backoff'] ?? '[]', true),
            'misfire' => $row['misfire_policy'] ?? 'smart',
            'rateLimitMax' => isset($row['rate_limit_max']) ? (int) $row['rate_limit_max'] : null,
            'rateLimitWindow' => isset($row['rate_limit_window']) ? (int) $row['rate_limit_window'] : null,
            'queue' => $row['queue'] ?? null,
            'connection' => $row['connection'] ?? null,
            'data' => json_decode($row['data'] ?? '[]', true),
            'startDate' => $row['start_date'] ?? null,
            'endDate' => $row['end_date'] ?? null,
            'tags' => json_decode($row['tags'] ?? '[]', true),
            'nextRunAt' => $row['next_run_at'] ?? null,
            'lastRunAt' => $row['last_run_at'] ?? null,
            'lastRunResult' => $row['last_run_result'] ?? null,
            'runCount' => (int) ($row['run_count'] ?? 0),
            'failCount' => (int) ($row['fail_count'] ?? 0),
            'status' => $row['status'] ?? 'active',
            'version' => (int) ($row['version'] ?? 0),
            'lockedUntil' => $row['locked_until'] ?? null,
            'nodeId' => $row['node_id'] ?? null,
            'lockedRunKey' => $row['locked_run_key'] ?? null,
        ];

        return ScheduleDefinition::fromArray($data, $this->registry);
    }
}
