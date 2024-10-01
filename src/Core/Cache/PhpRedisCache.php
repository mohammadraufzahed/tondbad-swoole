<?php

namespace TondbadSwoole\Core\Cache;

use TondbadSwoole\Core\Cache\Contracts\CacheInterface;
use Redis;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use TondbadSwoole\Core\Config;

class PhpRedisCache implements CacheInterface
{
    /**
     * @var Redis
     */
    private Redis $redis;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * Constructor
     *
     * @param array $config Redis client configuration.
     * @param SerializerInterface|null $serializer Optional serializer. If null, a default JSON serializer is used.
     */
    public function __construct()
    {
        $config = Config::get('cache.redis', []);

        $this->redis = new Redis();

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;

        // Connect to Redis server
        $connected = $this->redis->connect($host, $port, $timeout);
        if (!$connected) {
            throw new \Exception("Could not connect to Redis at {$host}:{$port}");
        }

        // Authenticate if password is provided
        if ($password !== null) {
            if (!$this->redis->auth($password)) {
                throw new \Exception("Redis authentication failed.");
            }
        }

        // Select the database
        if (!$this->redis->select($database)) {
            throw new \Exception("Could not select Redis database {$database}.");
        }

        // Initialize Serializer
        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $value = $this->redis->get($key);
        if ($value === false) {
            return null;
        }

        return $this->serializer->decode($value, 'json');
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

        if ($ttl !== null) {
            return $this->redis->setex($key, $ttl, $serializedValue);
        }

        return $this->redis->set($key, $serializedValue);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->redis->del([$key]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // WARNING: FLUSHDB removes all keys from the current database.
        // Use with caution. Alternatively, implement a safer key iteration and deletion.

        try {
            return $this->redis->flushDB();
        } catch (\Exception $e) {
            // Handle exception (e.g., log the error)
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys): array
    {
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys);
        $values = $this->redis->mget($keysArray);

        $results = [];
        foreach ($keysArray as $index => $key) {
            $value = $values[$index];
            $results[$key] = $value !== false ? $this->serializer->decode($value, 'json') : null;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $items, ?int $ttl = null): bool
    {
        $pipeline = $this->redis->multi(Redis::PIPELINE);

        foreach ($items as $key => $value) {
            try {
                $serializedValue = $this->serializer->encode($value, 'json');
            } catch (\Exception $e) {
                // Handle serialization errors (e.g., log the error)
                $this->redis->discard();
                return false;
            }

            if ($ttl !== null) {
                $pipeline->setex($key, $ttl, $serializedValue);
            } else {
                $pipeline->set($key, $serializedValue);
            }
        }

        $responses = $pipeline->exec();

        foreach ($responses as $response) {
            if ($response !== true) {
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
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys);
        return $this->redis->del($keysArray) > 0;
    }
}
