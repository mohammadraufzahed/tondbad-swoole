<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\Drivers;

use Predis\Client;
use Throwable;
use TondbadSwoole\Queue\Queue;
use TondbadSwoole\Queue\QueueEvents;
use TondbadSwoole\Queue\Jobs\Job;

class RedisQueue extends Queue
{
    public function __construct(
        private readonly Client $redis,
        private readonly string $prefix = 'tondbad',
        private readonly string $defaultQueue = 'default',
        private readonly int $retryAfter = 60,
        private readonly int $blockFor = 1,
        QueueEvents $events = new QueueEvents(),
    ) {
        parent::__construct($events);
    }

    public function push(Job $job, ?string $queue = null): mixed
    {
        $queue = $this->resolveQueue($job, $queue);
        $id = (int) $this->redis->incr($this->key('ids'));
        $now = time();
        $delay = $job->getDelay();
        $availableAt = $now + $delay;
        $isParent = $job->getChildrenCount() > 0;
        $status = $isParent ? 'waiting_children' : ($delay > 0 ? 'delayed' : 'waiting');
        $payload = serialize($job);

        $this->redis->hmset($this->jobKey($id), [
            'id' => (string) $id,
            'payload' => $payload,
            'queue' => $queue,
            'attempts' => '0',
            'status' => $status,
            'created_at' => (string) $now,
            'available_at' => (string) $availableAt,
            'reserved_at' => '0',
            'progress' => '0',
            'parent_id' => (string) ($job->getParentId() ?? 0),
            'children_count' => (string) ($job->getChildrenCount() ?? 0),
            'deduplication_id' => (string) ($job->getCustomJobId() ?? ''),
            'result' => '',
            'exception' => '',
            'remove_on_complete' => $job->shouldRemoveOnComplete() ? '1' : '0',
            'remove_on_fail' => $job->shouldRemoveOnFail() ? '1' : '0',
        ]);

        if ($job->getParentId() !== null) {
            $this->redis->executeRaw(['SADD', $this->key('children', (string) $job->getParentId()), (string) $id]);
        }

        if ($isParent) {
            $this->emit('added', ['job' => $job, 'queue' => $queue, 'id' => $id]);

            return $id;
        }

        if ($delay > 0) {
            $this->redis->executeRaw(['ZADD', $this->queueKey($queue, 'delayed'), (string) $availableAt, (string) $id]);
            $this->emit('delayed', ['job' => $job, 'queue' => $queue, 'id' => $id]);

            return $id;
        }

        $this->redis->executeRaw(['LPUSH', $this->queueKey($queue, 'waiting'), (string) $id]);
        $this->emit('added', ['job' => $job, 'queue' => $queue, 'id' => $id]);

        return $id;
    }

    public function pop(?string $queue = null): ?Job
    {
        $queue = $this->getQueue($queue);

        if ($this->isPaused($queue)) {
            return null;
        }

        $this->recoverAndPromote($queue);

        $waiting = $this->queueKey($queue, 'waiting');
        $active = $this->queueKey($queue, 'active');

        if ($this->blockFor > 0) {
            $id = $this->redis->brpoplpush($waiting, $active, $this->blockFor);
        } else {
            $id = $this->redis->rpoplpush($waiting, $active);
        }

        if ($id === null || $id === false) {
            return null;
        }

        $now = time();
        $this->redis->executeRaw(['ZADD', $this->queueKey($queue, 'active_set'), (string) $now, (string) $id]);
        $this->redis->hincrby($this->jobKey((int) $id), 'attempts', 1);
        $this->redis->hmset($this->jobKey((int) $id), ['reserved_at' => (string) $now, 'status' => 'active']);

        $job = $this->getJob((int) $id);

        if ($job === null) {
            $this->redis->executeRaw(['LREM', $active, '0', (string) $id]);
            $this->redis->executeRaw(['ZREM', $this->queueKey($queue, 'active_set'), (string) $id]);

            return null;
        }

        $job->setConnection($this);
        $job->setAttempts((int) $this->redis->hget($this->jobKey((int) $id), 'attempts'));
        $this->emit('active', ['job' => $job, 'queue' => $queue, 'id' => (int) $id]);

        return $job;
    }

    public function size(?string $queue = null): int
    {
        $queue = $this->getQueue($queue);

        return (int) $this->redis->llen($this->queueKey($queue, 'waiting'))
            + (int) $this->redis->zcard($this->queueKey($queue, 'delayed'));
    }

    public function delete(int $id): bool
    {
        $key = $this->jobKey($id);
        $hash = $this->redis->hgetall($key);
        $queue = $hash['queue'] ?? $this->defaultQueue;

        $this->redis->del([$key]);
        $this->redis->executeRaw(['LREM', $this->queueKey($queue, 'waiting'), '0', (string) $id]);
        $this->redis->executeRaw(['LREM', $this->queueKey($queue, 'active'), '0', (string) $id]);
        $this->redis->executeRaw(['ZREM', $this->queueKey($queue, 'active_set'), (string) $id]);
        $this->redis->executeRaw(['ZREM', $this->queueKey($queue, 'delayed'), (string) $id]);

        if ((int) ($hash['children_count'] ?? 0) > 0) {
            $this->redis->del([
                $this->key('children', (string) $id),
                $this->key('completed_children', (string) $id),
            ]);
        }

        return true;
    }

    public function release(?int $id, int $delay = 0): bool
    {
        if ($id === null) {
            return false;
        }

        $key = $this->jobKey($id);
        $hash = $this->redis->hgetall($key);

        if ($hash === []) {
            return false;
        }

        $queue = $hash['queue'] ?? $this->defaultQueue;

        $this->redis->executeRaw(['LREM', $this->queueKey($queue, 'active'), '0', (string) $id]);
        $this->redis->executeRaw(['ZREM', $this->queueKey($queue, 'active_set'), (string) $id]);

        if ($delay > 0) {
            $availableAt = time() + $delay;
            $this->redis->executeRaw(['ZADD', $this->queueKey($queue, 'delayed'), (string) $availableAt, (string) $id]);
            $this->redis->hmset($key, ['status' => 'delayed', 'reserved_at' => '0', 'available_at' => (string) $availableAt]);

            return true;
        }

        $this->redis->executeRaw(['LPUSH', $this->queueKey($queue, 'waiting'), (string) $id]);
        $this->redis->hmset($key, ['status' => 'waiting', 'reserved_at' => '0']);

        return true;
    }

    public function markCompleted(int $id): bool
    {
        $key = $this->jobKey($id);
        $hash = $this->redis->hgetall($key);

        if ($hash === []) {
            return false;
        }

        $queue = $hash['queue'] ?? $this->defaultQueue;
        $job = $this->unserializeJob($hash);
        $removeOnComplete = ($hash['remove_on_complete'] ?? '1') === '1';
        $parentId = (int) ($hash['parent_id'] ?? 0);

        if ($removeOnComplete) {
            $this->redis->del([$key]);

            if ((int) ($hash['children_count'] ?? 0) > 0) {
                $this->redis->del([
                    $this->key('children', (string) $id),
                    $this->key('completed_children', (string) $id),
                ]);
            }
        } else {
            $this->redis->hmset($key, ['status' => 'completed', 'reserved_at' => '0']);
        }

        $this->redis->executeRaw(['LREM', $this->queueKey($queue, 'active'), '0', (string) $id]);
        $this->redis->executeRaw(['ZREM', $this->queueKey($queue, 'active_set'), (string) $id]);
        $this->redis->hincrby($this->queueKey($queue, 'metrics'), 'completed', 1);

        if ($job !== null) {
            $this->emit('completed', ['job' => $job, 'queue' => $queue, 'id' => $id]);
        }

        if ($parentId > 0) {
            $this->redis->executeRaw(['SADD', $this->key('completed_children', (string) $parentId), (string) $id]);
            $this->releaseParent($parentId);
        }

        return true;
    }

    public function markFailed(int $id, ?Throwable $exception = null): bool
    {
        $key = $this->jobKey($id);
        $hash = $this->redis->hgetall($key);

        if ($hash === []) {
            return false;
        }

        $queue = $hash['queue'] ?? $this->defaultQueue;
        $job = $this->unserializeJob($hash);
        $removeOnFail = ($hash['remove_on_fail'] ?? '1') === '1';
        $parentId = (int) ($hash['parent_id'] ?? 0);
        $message = $exception !== null ? get_class($exception) . ': ' . $exception->getMessage() : '';

        if ($removeOnFail) {
            $this->redis->del([$key]);
        } else {
            $this->redis->hmset($key, ['status' => 'failed', 'reserved_at' => '0', 'exception' => $message]);
        }

        $this->redis->executeRaw(['LREM', $this->queueKey($queue, 'active'), '0', (string) $id]);
        $this->redis->executeRaw(['ZREM', $this->queueKey($queue, 'active_set'), (string) $id]);
        $this->redis->hincrby($this->queueKey($queue, 'metrics'), 'failed', 1);

        if ($job !== null) {
            $this->emit('failed', ['job' => $job, 'queue' => $queue, 'id' => $id, 'exception' => $exception]);
        }

        if ($parentId > 0) {
            $this->failParent($parentId);
        }

        return true;
    }

    public function progress(int $id, int $progress): bool
    {
        $key = $this->jobKey($id);

        if ((int) $this->redis->exists($key) === 0) {
            return false;
        }

        $this->redis->hset($key, 'progress', (string) max(0, min(100, $progress)));

        return true;
    }

    public function setResult(int $id, mixed $value): bool
    {
        $key = $this->jobKey($id);

        if ((int) $this->redis->exists($key) === 0) {
            return false;
        }

        $this->redis->hset($key, 'result', serialize($value));

        return true;
    }

    public function getChildren(int $parentId): array
    {
        $ids = $this->redis->smembers($this->key('children', (string) $parentId));
        $children = [];

        foreach ($ids as $id) {
            $hash = $this->redis->hgetall($this->jobKey((int) $id));

            if ($hash === []) {
                continue;
            }

            $job = $this->unserializeJob($hash);

            if ($job === null) {
                continue;
            }

            $result = null;

            if (isset($hash['result']) && $hash['result'] !== '') {
                $result = @unserialize($hash['result'], ['allowed_classes' => true]) ?: null;
            }

            $children[(int) $id] = [
                'job' => $job,
                'result' => $result,
                'status' => $hash['status'] ?? 'waiting',
            ];
        }

        return $children;
    }

    public function getJob(int $id): ?Job
    {
        $hash = $this->redis->hgetall($this->jobKey($id));

        if ($hash === []) {
            return null;
        }

        return $this->unserializeJob($hash);
    }

    public function getMetrics(?string $queue = null): array
    {
        $queue = $this->getQueue($queue);
        $metrics = $this->redis->hgetall($this->queueKey($queue, 'metrics'));

        $waiting = (int) $this->redis->llen($this->queueKey($queue, 'waiting'));
        $active = (int) $this->redis->zcard($this->queueKey($queue, 'active_set'));
        $delayed = (int) $this->redis->zcard($this->queueKey($queue, 'delayed'));
        $completed = (int) ($metrics['completed'] ?? 0);
        $failed = (int) ($metrics['failed'] ?? 0);

        return [
            'waiting' => $waiting,
            'active' => $active,
            'delayed' => $delayed,
            'completed' => $completed,
            'failed' => $failed,
            'total' => $waiting + $active + $delayed + $completed + $failed,
        ];
    }

    public function drain(?string $queue = null): int
    {
        $queue = $this->getQueue($queue);
        $waitingKey = $this->queueKey($queue, 'waiting');
        $delayedKey = $this->queueKey($queue, 'delayed');
        $activeKey = $this->queueKey($queue, 'active');

        $ids = array_merge(
            (array) $this->redis->lrange($waitingKey, 0, -1),
            (array) $this->redis->zrange($delayedKey, 0, -1),
            (array) $this->redis->lrange($activeKey, 0, -1),
        );

        foreach ($ids as $id) {
            $this->redis->del([$this->jobKey((int) $id)]);
        }

        $this->redis->del([$waitingKey, $delayedKey, $activeKey, $this->queueKey($queue, 'active_set'), $this->queueKey($queue, 'metrics')]);

        if ($ids !== []) {
            $this->emit('drained', ['queue' => $queue, 'count' => count($ids)]);
        }

        return count($ids);
    }

    public function clean(int $gracePeriod = 86400, ?string $queue = null): int
    {
        $queue = $this->getQueue($queue);
        $cutoff = time() - $gracePeriod;
        $cursor = 0;
        $removed = 0;
        $pattern = $this->key('job', '*');

        do {
            $scan = $this->redis->scan($cursor, ['match' => $pattern, 'count' => 100]);

            if (!is_array($scan) || count($scan) < 2) {
                break;
            }

            $cursor = (int) $scan[0];
            $keys = (array) $scan[1];

            foreach ($keys as $key) {
                $hash = $this->redis->hgetall($key);

                if ($hash === []) {
                    continue;
                }

                if (($hash['queue'] ?? $this->defaultQueue) !== $queue) {
                    continue;
                }

                $status = $hash['status'] ?? '';
                $updatedAt = max((int) ($hash['reserved_at'] ?? 0), (int) ($hash['created_at'] ?? 0));

                if (($status === 'completed' || $status === 'failed') && $updatedAt < $cutoff) {
                    $this->redis->del([$key]);
                    $removed++;
                }
            }
        } while ($cursor !== 0);

        if ($removed > 0) {
            $this->emit('cleaned', ['queue' => $queue, 'count' => $removed]);
        }

        return $removed;
    }

    public function pause(?string $queue = null): void
    {
        $queue = $this->getQueue($queue);
        $this->redis->set($this->queueKey($queue, 'paused'), '1');
        $this->emit('paused', ['queue' => $queue]);
    }

    public function resume(?string $queue = null): void
    {
        $queue = $this->getQueue($queue);
        $this->redis->del([$this->queueKey($queue, 'paused')]);
        $this->emit('resumed', ['queue' => $queue]);
    }

    private function resolveQueue(Job $job, ?string $queue): string
    {
        $q = $job->getQueue() ?? $queue ?? $this->defaultQueue;
        $job->onQueue($q);

        return $q;
    }

    private function getQueue(?string $queue): string
    {
        return $queue ?? $this->defaultQueue;
    }

    private function recoverAndPromote(string $queue): void
    {
        $script = <<<'LUA'
local now = tonumber(ARGV[1])
local retryAfter = tonumber(ARGV[2])
local active = KEYS[1]
local activeSet = KEYS[2]
local waiting = KEYS[3]
local delayed = KEYS[4]
local jobPrefix = KEYS[5] .. ':'

local stale = redis.call('ZRANGEBYSCORE', activeSet, '-inf', now - retryAfter)
for i = 1, #stale do
    local id = stale[i]
    redis.call('ZREM', activeSet, id)
    redis.call('LREM', active, 0, id)
    redis.call('RPUSH', waiting, id)
    redis.call('HMSET', jobPrefix .. id, 'reserved_at', 0, 'status', 'waiting')
end

local ready = redis.call('ZRANGEBYSCORE', delayed, '-inf', now)
for i = 1, #ready do
    local id = ready[i]
    redis.call('ZREM', delayed, id)
    redis.call('RPUSH', waiting, id)
    redis.call('HMSET', jobPrefix .. id, 'status', 'waiting')
end

return #stale + #ready
LUA;

        $this->redis->executeRaw(array_merge(
            ['EVAL', $script, '5'],
            [
                $this->queueKey($queue, 'active'),
                $this->queueKey($queue, 'active_set'),
                $this->queueKey($queue, 'waiting'),
                $this->queueKey($queue, 'delayed'),
                $this->key('job'),
                (string) time(),
                (string) $this->retryAfter,
            ]
        ));
    }

    private function releaseParent(int $parentId): void
    {
        $childrenKey = $this->key('children', (string) $parentId);
        $completedKey = $this->key('completed_children', (string) $parentId);

        $childrenCount = (int) $this->redis->scard($childrenKey);
        $completedCount = (int) $this->redis->scard($completedKey);

        if ($completedCount < $childrenCount || $childrenCount === 0) {
            return;
        }

        $parentKey = $this->jobKey($parentId);
        $parentHash = $this->redis->hgetall($parentKey);

        if ($parentHash === []) {
            return;
        }

        if (($parentHash['status'] ?? '') !== 'waiting_children') {
            return;
        }

        $queue = $parentHash['queue'] ?? $this->defaultQueue;

        $this->redis->hmset($parentKey, ['status' => 'waiting']);
        $this->redis->executeRaw(['LPUSH', $this->queueKey($queue, 'waiting'), (string) $parentId]);

        $parentJob = $this->unserializeJob($parentHash);

        if ($parentJob !== null) {
            $this->emit('added', ['job' => $parentJob, 'queue' => $queue, 'id' => $parentId]);
        }
    }

    private function failParent(int $parentId): void
    {
        $parentKey = $this->jobKey($parentId);
        $parentHash = $this->redis->hgetall($parentKey);

        if ($parentHash === []) {
            return;
        }

        if (($parentHash['status'] ?? '') !== 'waiting_children') {
            return;
        }

        $this->redis->hmset($parentKey, ['status' => 'failed']);

        $parentJob = $this->unserializeJob($parentHash);

        if ($parentJob !== null) {
            $this->emit('failed', ['job' => $parentJob, 'queue' => $parentHash['queue'] ?? $this->defaultQueue, 'id' => $parentId]);
        }
    }

    private function unserializeJob(array $hash): ?Job
    {
        if (!isset($hash['payload']) || $hash['payload'] === '') {
            return null;
        }

        $job = @unserialize($hash['payload'], ['allowed_classes' => true]);

        if (!$job instanceof Job) {
            return null;
        }

        $job->setJobId((int) ($hash['id'] ?? 0));
        $job->setAttempts((int) ($hash['attempts'] ?? 0));
        $job->onQueue($hash['queue'] ?? $this->defaultQueue);

        return $job;
    }

    private function isPaused(string $queue): bool
    {
        return (int) $this->redis->exists($this->queueKey($queue, 'paused')) === 1;
    }

    private function key(string ...$parts): string
    {
        return implode(':', array_merge([$this->prefix, 'queue'], $parts));
    }

    private function queueKey(string $queue, string $type): string
    {
        return $this->key($queue, $type);
    }

    private function jobKey(int $id): string
    {
        return $this->key('job', (string) $id);
    }
}
