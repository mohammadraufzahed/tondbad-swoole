<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

use DateInterval;
use OpenSwoole\Table;
use OpenSwoole\Timer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use TondbadSwoole\Contracts\CacheInterface;

class InMemoryCache implements CacheInterface
{
    private Table $table;
    private int $size;
    private int $cleanInterval;
    private SerializerInterface $serializer;

    public function __construct(
        int $size = 1024,
        int $cleanInterval = 1000,
        ?SerializerInterface $serializer = null
    ) {
        $this->cleanInterval = $cleanInterval;
        $this->size = $size;

        $this->table = new Table($size);
        $this->table->column('value', Table::TYPE_STRING, 65535);
        $this->table->column('expires_at', Table::TYPE_INT, 10);
        $this->table->create();

        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        Timer::tick($this->cleanInterval, function () {
            $this->cleanExpiredItems();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        $data = $this->table->get($key);

        return $this->serializer->decode($data['value'], 'json');
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        try {
            $serializedValue = $this->serializer->encode($value, 'json');
        } catch (\Exception $e) {
            return false;
        }

        if ($ttl instanceof DateInterval) {
            $expiresAt = time() + $this->ttlToSeconds($ttl);
        } elseif (is_int($ttl)) {
            if ($ttl <= 0) {
                return $this->delete($key);
            }

            $expiresAt = time() + $ttl;
        } else {
            $expiresAt = 0;
        }

        $data = [
            'value' => $serializedValue,
            'expires_at' => $expiresAt,
        ];

        if ($this->has($key)) {
            return $this->table->set($key, $data);
        }

        return $this->trySet($key, $data);
    }

    private function trySet(string $key, array $data): bool
    {
        $this->evictIfNeeded();

        if ($this->table->set($key, $data)) {
            return true;
        }

        $this->cleanExpiredItems();
        $this->evictIfNeeded();

        if ($this->table->set($key, $data)) {
            return true;
        }

        $this->evictToSize((int) ceil($this->size / 2));

        return $this->table->set($key, $data);
    }

    private function evictIfNeeded(): void
    {
        if (count($this->table) >= $this->size) {
            $this->evictToSize($this->size - 1);
        }
    }

    private function evictToSize(int $target): void
    {
        if ($target < 0) {
            return;
        }

        $toRemove = count($this->table) - $target;

        if ($toRemove <= 0) {
            return;
        }

        $keys = [];
        foreach ($this->table as $existingKey => $row) {
            $keys[] = (string) $existingKey;

            if (count($keys) >= $toRemove) {
                break;
            }
        }

        foreach ($keys as $existingKey) {
            $this->table->del($existingKey);
        }
    }

    public function delete(string $key): bool
    {
        $this->table->del($key);

        return true;
    }

    public function clear(): bool
    {
        foreach ($this->table as $key => $row) {
            $this->table->del((string) $key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $data = $this->table->get($key);

        if ($data === false) {
            return false;
        }

        if ($data['expires_at'] !== 0 && time() > $data['expires_at']) {
            $this->delete($key);

            return false;
        }

        return true;
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

    private function cleanExpiredItems(): void
    {
        $currentTime = time();

        foreach ($this->table as $key => $row) {
            if ($row['expires_at'] !== 0 && $currentTime > $row['expires_at']) {
                $this->table->del((string) $key);
            }
        }
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
