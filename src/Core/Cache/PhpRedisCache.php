<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

use DateInterval;
use Redis;
use RuntimeException;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Config;

class PhpRedisCache implements CacheInterface
{
    private Redis $redis;
    private SerializerInterface $serializer;
    private string $prefix;

    public function __construct(
        private readonly Config $config,
        ?SerializerInterface $serializer = null
    ) {
        $redisConfig = $config->get('cache.redis', []);

        $this->redis = new Redis();

        $host = $redisConfig['host'] ?? '127.0.0.1';
        $port = $redisConfig['port'] ?? 6379;
        $timeout = $redisConfig['timeout'] ?? 0.0;
        $password = $redisConfig['password'] ?? null;
        $database = $redisConfig['database'] ?? 0;

        $connected = $this->redis->connect($host, $port, $timeout);
        if (!$connected) {
            throw new RuntimeException("Could not connect to Redis at {$host}:{$port}");
        }

        if ($password !== null && !$this->redis->auth($password)) {
            throw new RuntimeException('Redis authentication failed.');
        }

        if (!$this->redis->select($database)) {
            throw new RuntimeException("Could not select Redis database {$database}.");
        }

        $this->prefix = $redisConfig['options']['prefix'] ?? '';
        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->redis->get($this->prefixKey($key));

        if ($value === false) {
            return $default;
        }

        return $this->serializer->decode($value, 'json');
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        try {
            $serializedValue = $this->serializer->encode($value, 'json');
        } catch (\Exception $e) {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);

        if ($ttl !== null) {
            return $this->redis->setex($prefixedKey, $this->ttlToSeconds($ttl), $serializedValue);
        }

        return $this->redis->set($prefixedKey, $serializedValue);
    }

    public function delete(string $key): bool
    {
        $this->redis->del([$this->prefixKey($key)]);

        return true;
    }

    public function clear(): bool
    {
        if ($this->prefix === '') {
            try {
                return $this->redis->flushDB();
            } catch (\Exception $e) {
                return false;
            }
        }

        try {
            $iterator = null;

            do {
                $keys = $this->redis->scan($iterator, $this->prefix . '*', 100);

                if ($keys === false) {
                    break;
                }

                if (!empty($keys)) {
                    $this->redis->del($keys);
                }
            } while ($iterator !== 0);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        return $this->redis->exists($this->prefixKey($key)) > 0;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys, false);
        $prefixedKeys = [];
        $keyMap = [];

        foreach ($keysArray as $key) {
            $pk = $this->prefixKey((string) $key);
            $prefixedKeys[] = $pk;
            $keyMap[$pk] = (string) $key;
        }

        $values = $this->redis->mget($prefixedKeys);

        $results = [];
        foreach ($prefixedKeys as $index => $pk) {
            $value = $values[$index];
            $results[$keyMap[$pk]] = $value !== false ? $this->serializer->decode($value, 'json') : $default;
        }

        return $results;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $pipeline = $this->redis->multi(Redis::PIPELINE);

        foreach ($values as $key => $value) {
            try {
                $serializedValue = $this->serializer->encode($value, 'json');
            } catch (\Exception $e) {
                $this->redis->discard();

                return false;
            }

            $prefixedKey = $this->prefixKey((string) $key);

            if ($ttl !== null) {
                $pipeline->setex($prefixedKey, $this->ttlToSeconds($ttl), $serializedValue);
            } else {
                $pipeline->set($prefixedKey, $serializedValue);
            }
        }

        $responses = $pipeline->exec();

        if ($responses === false) {
            return false;
        }

        foreach ($responses as $response) {
            if ($response !== true && $response !== 1 && $response !== 'OK') {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys, false);
        $prefixedKeys = array_map(fn($key) => $this->prefixKey((string) $key), $keysArray);

        $this->redis->del($prefixedKeys);

        return true;
    }

    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
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
