<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling\Stores;

use DateTimeImmutable;
use DateTimeInterface;
use Predis\Client;
use TondbadSwoole\Core\Config;
use TondbadSwoole\Scheduling\Contracts\ScheduleStore;
use TondbadSwoole\Scheduling\ScheduleDefinition;
use TondbadSwoole\Scheduling\ScheduleRegistry;

class RedisScheduleStore implements ScheduleStore
{
    private readonly Client $redis;

    public function __construct(
        private readonly Config $config,
        private readonly ScheduleRegistry $registry,
        ?Client $redis = null,
        private readonly string $prefix = 'tondbad:schedule',
    ) {
        $this->redis = $redis ?? new Client($this->buildParameters($this->config->get('cache.redis', [])));
    }

    public function all(): array
    {
        $ids = $this->redis->smembers("{$this->prefix}:ids");

        return array_values(array_filter(array_map(fn (string $id) => $this->find($id), $ids)));
    }

    public function find(string $id): ?ScheduleDefinition
    {
        $data = $this->redis->hgetall($this->key($id));

        if (empty($data)) {
            return null;
        }

        return $this->hydrate($data);
    }

    public function upsert(ScheduleDefinition $definition): void
    {
        $data = $definition->toArray();

        $this->redis->hmset($this->key($definition->id), $this->flatten($data));
        $this->redis->sadd("{$this->prefix}:ids", $definition->id);
    }

    public function delete(string $id): void
    {
        $this->redis->del([$this->key($id)]);
        $this->redis->srem("{$this->prefix}:ids", $id);
    }

    public function pause(string $id): void
    {
        $this->redis->hset($this->key($id), 'status', 'paused');
    }

    public function resume(string $id): void
    {
        $this->redis->hset($this->key($id), 'status', 'active');
    }

    public function due(DateTimeInterface $before): array
    {
        return $this->all();
    }

    public function claim(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool
    {
        $lockKey = $this->lockKey($id, $runKey);
        $lease = $expiresAt->getTimestamp() - time();

        if ($lease <= 0) {
            $lease = 60;
        }

        $result = $this->redis->set($lockKey, $nodeId, 'EX', $lease, 'NX');

        if ($result === null || $result === false) {
            return false;
        }

        $this->redis->hmset($this->key($id), [
            'locked_until' => $expiresAt->format('c'),
            'node_id' => $nodeId,
            'locked_run_key' => $runKey,
        ]);

        return true;
    }

    public function release(string $id, string $nodeId, string $runKey): void
    {
        $lockKey = $this->lockKey($id, $runKey);

        if ((string) $this->redis->get($lockKey) === $nodeId) {
            $this->redis->del([$lockKey]);
        }

        $this->redis->hmset($this->key($id), [
            'locked_until' => '',
            'node_id' => '',
            'locked_run_key' => '',
        ]);
    }

    public function heartbeat(string $id, string $nodeId, string $runKey, DateTimeInterface $expiresAt): bool
    {
        $lockKey = $this->lockKey($id, $runKey);

        if ((string) $this->redis->get($lockKey) !== $nodeId) {
            return false;
        }

        $lease = $expiresAt->getTimestamp() - time();

        if ($lease <= 0) {
            $lease = 60;
        }

        $this->redis->expire($lockKey, $lease);
        $this->redis->hset($this->key($id), 'locked_until', $expiresAt->format('c'));

        return true;
    }

    private function key(string $id): string
    {
        return "{$this->prefix}:{$id}";
    }

    private function lockKey(string $id, string $runKey): string
    {
        return "{$this->prefix}:lock:{$id}:{$runKey}";
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string>
     */
    private function flatten(array $data): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $flat[$key] = json_encode($value);
            } elseif ($value === null) {
                $flat[$key] = '';
            } elseif ($value instanceof \DateTimeInterface) {
                $flat[$key] = $value->format('c');
            } else {
                $flat[$key] = (string) $value;
            }
        }

        return $flat;
    }

    /**
     * @param array<string, string> $data
     */
    private function hydrate(array $data): ?ScheduleDefinition
    {
        $normalized = [];

        foreach ($data as $key => $value) {
            if ($key === 'trigger' || $key === 'task' || $key === 'tags' || $key === 'data' || $key === 'backoff') {
                $normalized[$key] = json_decode($value ?: '{}', true);
            } elseif (in_array($key, ['nextRunAt', 'lastRunAt', 'startDate', 'endDate', 'lockedUntil'], true)) {
                $normalized[$key] = $value !== '' ? $value : null;
            } else {
                $normalized[$key] = $value;
            }
        }

        return ScheduleDefinition::fromArray($normalized, $this->registry);
    }

    /**
     * @param array<string, mixed> $redisConfig
     *
     * @return array<string, mixed>
     */
    private function buildParameters(array $redisConfig): array
    {
        $parameters = [
            'scheme' => $redisConfig['scheme'] ?? 'tcp',
            'host' => $redisConfig['host'] ?? '127.0.0.1',
            'port' => (int) ($redisConfig['port'] ?? 6379),
            'database' => (int) ($redisConfig['database'] ?? 0),
        ];

        if (!empty($redisConfig['password'])) {
            $parameters['password'] = $redisConfig['password'];
        }

        return $parameters;
    }
}
