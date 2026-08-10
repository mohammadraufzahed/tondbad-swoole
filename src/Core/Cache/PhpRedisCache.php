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
    private Redis $redis;
    private SerializerInterface $serializer;
    private string $prefix;

    public function __construct(?SerializerInterface $serializer = null)
    {
        $config = Config::get('cache.redis', []);

        $this->redis = new Redis();

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $timeout = $config['timeout'] ?? 0.0;
        $password = $config['password'] ?? null;
        $database = $config['database'] ?? 0;

        $connected = $this->redis->connect($host, $port, $timeout);
        if (!$connected) {
            throw new \Exception("Could not connect to Redis at {$host}:{$port}");
        }

        if ($password !== null) {
            if (!$this->redis->auth($password)) {
                throw new \Exception("Redis authentication failed.");
            }
        }

        if (!$this->redis->select($database)) {
            throw new \Exception("Could not select Redis database {$database}.");
        }

        $this->prefix = Config::get('cache.redis.options.prefix', '');
        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    public function get(string $key): mixed
    {
        $value = $this->redis->get($this->prefixKey($key));
        if ($value === false) {
            return null;
        }

        return $this->serializer->decode($value, 'json');
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        try {
            $serializedValue = $this->serializer->encode($value, 'json');
        } catch (\Exception $e) {
            return false;
        }

        $prefixedKey = $this->prefixKey($key);

        if ($ttl !== null) {
            return $this->redis->setex($prefixedKey, $ttl, $serializedValue);
        }

        return $this->redis->set($prefixedKey, $serializedValue);
    }

    public function delete(string $key): bool
    {
        return $this->redis->del([$this->prefixKey($key)]) > 0;
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

    public function getMultiple(iterable $keys): array
    {
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys);
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
            $results[$keyMap[$pk]] = $value !== false ? $this->serializer->decode($value, 'json') : null;
        }

        return $results;
    }

    public function setMultiple(iterable $items, ?int $ttl = null): bool
    {
        $pipeline = $this->redis->multi(Redis::PIPELINE);

        foreach ($items as $key => $value) {
            try {
                $serializedValue = $this->serializer->encode($value, 'json');
            } catch (\Exception $e) {
                $this->redis->discard();
                return false;
            }

            $prefixedKey = $this->prefixKey((string) $key);

            if ($ttl !== null) {
                $pipeline->setex($prefixedKey, $ttl, $serializedValue);
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
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys);
        $prefixedKeys = array_map(fn($key) => $this->prefixKey((string) $key), $keysArray);
        return $this->redis->del($prefixedKeys) > 0;
    }

    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
