<?php

namespace TondbadSwoole\Core\Cache;

use OpenSwoole\Table;
use OpenSwoole\Timer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use TondbadSwoole\Core\Cache\Contracts\CacheInterface;

/**
 * Class OpenSwooleTableCache
 *
 * Implements the Cache interface using OpenSwoole's Table for in-memory caching with serialization support.
 */
class InMemoryCache implements CacheInterface
{
    /**
     * @var Table
     */
    private Table $table;

    /**
     * @var int Timer interval for cleaning expired items (in milliseconds)
     */
    private int $cleanInterval;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * Constructor
     *
     * @param int $size Number of items the cache can hold
     * @param int $cleanInterval Interval to clean expired items (in milliseconds)
     * @param SerializerInterface|null $serializer Optional serializer. If null, a default JSON serializer is used.
     */
    public function __construct(
        int $size = 1024,
        int $cleanInterval = 1000,
        ?SerializerInterface $serializer = null
    ) {
        $this->cleanInterval = $cleanInterval;

        // Initialize OpenSwoole\Table
        $this->table = new Table($size);
        $this->table->column('value', Table::TYPE_STRING, 65535); // Max value size
        $this->table->column('expires_at', Table::TYPE_INT, 10); // Unix timestamp
        $this->table->create();

        // Initialize Serializer
        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        // Start a timer to clean expired items periodically
        Timer::tick($this->cleanInterval, function () {
            $this->cleanExpiredItems();
        });
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        if (!$this->has($key)) {
            return null;
        }

        $data = $this->table->get($key);
        return $this->serializer->decode($data['value'], 'json');
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $serializedValue = $this->serializer->encode($value, 'json');
        } catch (\Exception $e) {
            // Handle serialization errors (e.g., log the error)
            return false;
        }

        $expiresAt = $ttl !== null ? time() + $ttl : 0;

        return $this->table->set($key, [
            'value' => $serializedValue,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->table->del($key) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        foreach ($this->table as $key => $row) {
            $this->table->del($key);
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
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

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys): array
    {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key);
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $items, ?int $ttl = null): bool
    {
        foreach ($items as $key => $value) {
            if (!$this->set((string) $key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete((string) $key);
        }
        return true;
    }

    /**
     * Clean expired items from the cache.
     *
     * @return void
     */
    private function cleanExpiredItems(): void
    {
        $currentTime = time();

        foreach ($this->table as $key => $row) {
            if ($row['expires_at'] !== 0 && $currentTime > $row['expires_at']) {
                $this->table->del($key);
            }
        }
    }
}
