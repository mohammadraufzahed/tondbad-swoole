<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

use DateInterval;
use TondbadSwoole\Contracts\Cache\CacheItem;
use TondbadSwoole\Contracts\Cache\CacheStats;
use TondbadSwoole\Contracts\CacheContract;
use TondbadSwoole\Contracts\CacheInterface;

class HybridStore implements CacheContract
{
    private CacheStats $stats;

    public function __construct(
        private readonly CacheInterface $l1,
        private readonly ?CacheInterface $l2 = null,
        private readonly ?Serializer $serializer = null,
        private readonly ?TagManager $tagManager = null,
        private readonly ?Lock $lock = null,
        private readonly int $lockWaitMs = 5000,
    ) {
        $this->stats = new CacheStats();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->getEntry($key);

        return $entry === null ? $default : $this->deserializeValue($entry->value);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $item = new CacheItem($key);

        if ($ttl !== null) {
            $item->lifetime($this->ttlToSeconds($ttl));
        }

        return $this->store($key, $value, $item);
    }

    public function delete(string $key): bool
    {
        $this->l1->delete($key);

        if ($this->l2 !== null) {
            $this->l2->delete($key);
        }

        return true;
    }

    public function clear(): bool
    {
        $this->l1->clear();

        if ($this->l2 !== null) {
            $this->l2->clear();
        }

        return true;
    }

    public function has(string $key): bool
    {
        return $this->getEntry($key) !== null;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $results = [];

        foreach ($keys as $key) {
            $results[(string) $key] = $this->get((string) $key, $default);
        }

        return $results;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }

        return true;
    }

    public function getOrSet(string $key, callable $callback, ?int $ttl = null): mixed
    {
        $entry = $this->getEntry($key, false);

        if ($entry !== null && !$this->isRefreshDue($entry)) {
            $this->stats->recordHit(true);

            return $this->deserializeValue($entry->value);
        }

        if ($this->lock !== null && $this->lock->acquire($key, $this->lockWaitMs)) {
            try {
                $entry = $this->getEntry($key);

                if ($entry !== null && !$this->isRefreshDue($entry)) {
                    return $this->deserializeValue($entry->value);
                }

                return $this->load($key, $callback, $ttl);
            } finally {
                $this->lock->release($key);
            }
        }

        $entry = $this->getEntry($key);

        if ($entry !== null && !$this->isRefreshDue($entry)) {
            return $this->deserializeValue($entry->value);
        }

        return $this->load($key, $callback, $ttl);
    }

    public function invalidateTags(array $tags): bool
    {
        $this->tagManager?->invalidate($tags);
        $this->l1->clear();

        return true;
    }

    public function refresh(string $key): bool
    {
        $this->stats->recordRefresh();

        return $this->delete($key);
    }

    public function stats(): CacheStats
    {
        return $this->stats;
    }

    private function getEntry(string $key, bool $recordStats = true): ?CacheEntry
    {
        $raw = $this->l1->get($key);

        if ($raw !== null) {
            $entry = $this->unserializeEntry($raw);

            if ($entry !== null && !$this->isExpired($entry) && $this->isTagsValid($entry)) {
                if ($recordStats) {
                    $this->stats->recordHit(true);
                }

                return $entry;
            }

            $this->l1->delete($key);
        }

        if ($this->l2 !== null) {
            $raw = $this->l2->get($key);

            if ($raw !== null) {
                $entry = $this->unserializeEntry($raw);

                if ($entry !== null && !$this->isExpired($entry) && $this->isTagsValid($entry)) {
                    $this->l1->set($key, $raw, $this->entryTtl($entry));

                    if ($recordStats) {
                        $this->stats->recordHit(false);
                    }

                    return $entry;
                }
            }
        }

        if ($recordStats) {
            $this->stats->recordMiss();
        }

        return null;
    }

    private function load(string $key, callable $callback, ?int $ttl): mixed
    {
        $item = new CacheItem($key);
        $started = microtime(true);

        try {
            $value = $callback($item);
        } catch (\Throwable $e) {
            $this->stats->recordLoad(microtime(true) - $started, false);

            throw $e;
        }

        $this->stats->recordLoad(microtime(true) - $started, true);

        if ($ttl !== null && $item->lifetime === 0) {
            $item->lifetime($ttl);
        }

        $this->store($key, $value, $item);

        return $value;
    }

    private function store(string $key, mixed $value, CacheItem $item): bool
    {
        $tagVersions = $this->tagManager !== null ? $this->tagManager->getVersions($item->tags) : [];

        $entry = new CacheEntry(
            $key,
            $this->serializeValue($value),
            time(),
            $item->expiresAt(),
            $item->refreshAt(),
            $item->tags,
            $tagVersions,
            $item->weight,
            $item->metadata,
        );

        $raw = serialize($entry);
        $ttl = $this->entryTtl($entry);

        if ($this->l2 !== null) {
            $this->l2->set($key, $raw, $ttl);
        }

        return $this->l1->set($key, $raw, $ttl);
    }

    private function unserializeEntry(mixed $raw): ?CacheEntry
    {
        if (!is_string($raw)) {
            return null;
        }

        $entry = @unserialize($raw);

        return $entry instanceof CacheEntry ? $entry : null;
    }

    private function isExpired(CacheEntry $entry): bool
    {
        return $entry->expiresAt !== 0 && time() > $entry->expiresAt;
    }

    private function isRefreshDue(CacheEntry $entry): bool
    {
        return $entry->refreshAt !== 0 && time() >= $entry->refreshAt;
    }

    private function isTagsValid(CacheEntry $entry): bool
    {
        if ($this->tagManager === null || $entry->tags === []) {
            return true;
        }

        $currentVersions = $this->tagManager->getVersions($entry->tags);

        foreach ($entry->tags as $tag) {
            $entryVersion = $entry->tagVersions[$tag] ?? 0;
            $currentVersion = $currentVersions[$tag] ?? 0;

            if ($entryVersion !== $currentVersion) {
                return false;
            }
        }

        return true;
    }

    private function entryTtl(CacheEntry $entry): ?int
    {
        if ($entry->expiresAt === 0) {
            return null;
        }

        $remaining = $entry->expiresAt - time();

        return max(1, $remaining);
    }

    private function serializeValue(mixed $value): string
    {
        return ($this->serializer ?? new JsonSerializer())->serialize($value);
    }

    private function deserializeValue(string $value): mixed
    {
        return ($this->serializer ?? new JsonSerializer())->deserialize($value);
    }

    private function ttlToSeconds(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        if (is_int($ttl)) {
            return $ttl;
        }

        $now = new \DateTime();
        $end = clone $now;
        $end->add($ttl);

        return $end->getTimestamp() - $now->getTimestamp();
    }
}
