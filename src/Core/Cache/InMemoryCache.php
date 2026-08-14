<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

use DateInterval;
use OpenSwoole\Coroutine;
use OpenSwoole\Table;
use OpenSwoole\Timer;
use TondbadSwoole\Contracts\CacheInterface;

class InMemoryCache implements CacheInterface
{
    private Table $table;
    private int $size;
    private int $cleanInterval;
    private string $dataColumn = 'data';
    private string $expiresColumn = 'expires_at';
    private string $lastAccessColumn = 'last_access';

    public function __construct(int $size = 1024, int $cleanInterval = 1000)
    {
        $this->size = $size;
        $this->cleanInterval = $cleanInterval;

        $this->table = new Table($size);
        $this->table->column($this->dataColumn, Table::TYPE_STRING, 65535);
        $this->table->column($this->expiresColumn, Table::TYPE_INT, 10);
        $this->table->column($this->lastAccessColumn, Table::TYPE_INT, 10);
        $this->table->create();

        if ($this->cleanInterval > 0 && Coroutine::getCid() !== -1) {
            Timer::tick($this->cleanInterval, function () {
                $this->cleanExpiredItems();
            });
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        $row = $this->table->get($key);

        return $this->decode($row[$this->dataColumn]);
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $expiresAt = $this->ttlToTimestamp($ttl);

        $row = [
            $this->dataColumn => $this->encode($value),
            $this->expiresColumn => $expiresAt,
            $this->lastAccessColumn => time(),
        ];

        if ($this->has($key)) {
            return $this->table->set($key, $row);
        }

        return $this->trySet($key, $row);
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
        $row = $this->table->get($key);

        if ($row === false) {
            return false;
        }

        if ($row[$this->expiresColumn] !== 0 && time() > $row[$this->expiresColumn]) {
            $this->delete($key);

            return false;
        }

        $this->touch($key, $row);

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

    private function trySet(string $key, array $row): bool
    {
        $this->evictIfNeeded();

        if ($this->table->set($key, $row)) {
            return true;
        }

        $this->cleanExpiredItems();
        $this->evictIfNeeded();

        if ($this->table->set($key, $row)) {
            return true;
        }

        $this->evictToSize((int) ceil($this->size / 2));

        return $this->table->set($key, $row);
    }

    private function evictIfNeeded(): void
    {
        if (count($this->table) >= $this->size) {
            $this->evictOne();
        }
    }

    private function evictToSize(int $target): void
    {
        $toRemove = count($this->table) - $target;

        while ($toRemove-- > 0 && count($this->table) > 0) {
            $this->evictOne();
        }
    }

    private function evictOne(): void
    {
        $oldestKey = null;
        $oldestAccess = PHP_INT_MAX;

        foreach ($this->table as $key => $row) {
            if ($row[$this->expiresColumn] !== 0 && time() > $row[$this->expiresColumn]) {
                $this->table->del((string) $key);

                return;
            }

            if ($row[$this->lastAccessColumn] < $oldestAccess) {
                $oldestAccess = $row[$this->lastAccessColumn];
                $oldestKey = (string) $key;
            }
        }

        if ($oldestKey !== null) {
            $this->table->del($oldestKey);
        }
    }

    private function touch(string $key, array $row): void
    {
        $this->table->set($key, [
            $this->dataColumn => $row[$this->dataColumn],
            $this->expiresColumn => $row[$this->expiresColumn],
            $this->lastAccessColumn => time(),
        ]);
    }

    private function cleanExpiredItems(): void
    {
        $now = time();

        foreach ($this->table as $key => $row) {
            if ($row[$this->expiresColumn] !== 0 && $now > $row[$this->expiresColumn]) {
                $this->table->del((string) $key);
            }
        }
    }

    private function encode(mixed $value): string
    {
        if (is_string($value)) {
            return 'R:' . $value;
        }

        return 'S:' . serialize($value);
    }

    private function decode(string $data): mixed
    {
        if (str_starts_with($data, 'R:')) {
            return substr($data, 2);
        }

        if (str_starts_with($data, 'S:')) {
            return unserialize(substr($data, 2));
        }

        return $data;
    }

    private function ttlToTimestamp(null|int|DateInterval $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        if (is_int($ttl)) {
            return $ttl <= 0 ? 0 : time() + $ttl;
        }

        $now = new \DateTime();
        $end = clone $now;
        $end->add($ttl);

        return $end->getTimestamp();
    }
}
