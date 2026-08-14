<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

use DateInterval;
use OpenSwoole\Coroutine\Channel;
use Predis\Client as PredisClient;
use Predis\Collection\Iterator\Keyspace;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Config;

class RedisCache implements CacheInterface
{
    private Channel $pool;
    private int $poolSize;
    private int $created = 0;
    private string $prefix;
    private array $parameters;
    private Serializer $serializer;

    public function __construct(
        private readonly Config $config,
        ?Serializer $serializer = null,
    ) {
        $redisConfig = $config->get('cache.redis', []);

        $this->poolSize = (int) ($redisConfig['pool']['size'] ?? 4);
        $this->pool = new Channel($this->poolSize);
        $this->prefix = $redisConfig['options']['prefix'] ?? '';
        $this->parameters = $this->buildParameters($redisConfig);
        $this->serializer = $serializer ?? new JsonSerializer();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $client = $this->borrow();

        try {
            $value = $client->get($this->prefixKey($key));

            if ($value === null) {
                return $default;
            }

            return $this->serializer->deserialize($value);
        } finally {
            $this->release($client);
        }
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $client = $this->borrow();

        try {
            $serialized = $this->serializer->serialize($value);
            $prefixedKey = $this->prefixKey($key);

            if ($ttl !== null) {
                $seconds = $this->ttlToSeconds($ttl);

                if ($seconds <= 0) {
                    return $this->delete($key);
                }

                return (string) $client->setex($prefixedKey, $seconds, $serialized) === 'OK';
            }

            return (string) $client->set($prefixedKey, $serialized) === 'OK';
        } finally {
            $this->release($client);
        }
    }

    public function delete(string $key): bool
    {
        $client = $this->borrow();

        try {
            $client->del([$this->prefixKey($key)]);

            return true;
        } finally {
            $this->release($client);
        }
    }

    public function clear(): bool
    {
        $client = $this->borrow();

        try {
            if ($this->prefix === '') {
                return (string) $client->flushdb() === 'OK';
            }

            $batch = [];

            foreach (new Keyspace($client, $this->prefix . '*', 100) as $key) {
                $batch[] = (string) $key;

                if (count($batch) === 100) {
                    $client->del($batch);
                    $batch = [];
                }
            }

            if ($batch !== []) {
                $client->del($batch);
            }

            return true;
        } finally {
            $this->release($client);
        }
    }

    public function has(string $key): bool
    {
        $client = $this->borrow();

        try {
            return $client->exists([$this->prefixKey($key)]) > 0;
        } finally {
            $this->release($client);
        }
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $client = $this->borrow();

        try {
            $keysArray = is_array($keys) ? $keys : iterator_to_array($keys, false);
            $prefixedKeys = array_map(fn($key) => $this->prefixKey((string) $key), $keysArray);
            $values = $client->mget($prefixedKeys);

            $results = [];
            foreach ($keysArray as $index => $key) {
                $value = $values[$index];
                $results[(string) $key] = $value !== null ? $this->serializer->deserialize($value) : $default;
            }

            return $results;
        } finally {
            $this->release($client);
        }
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $client = $this->borrow();

        try {
            $pipeline = $client->pipeline();

            foreach ($values as $key => $value) {
                $serialized = $this->serializer->serialize($value);
                $prefixedKey = $this->prefixKey((string) $key);

                if ($ttl !== null) {
                    $seconds = $this->ttlToSeconds($ttl);
                    if ($seconds <= 0) {
                        $this->release($client);

                        return false;
                    }

                    $pipeline->setex($prefixedKey, $seconds, $serialized);
                } else {
                    $pipeline->set($prefixedKey, $serialized);
                }
            }

            $responses = $pipeline->execute();

            foreach ($responses as $response) {
                if ((string) $response !== 'OK') {
                    return false;
                }
            }

            return true;
        } finally {
            $this->release($client);
        }
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $client = $this->borrow();

        try {
            $keysArray = is_array($keys) ? $keys : iterator_to_array($keys, false);
            $prefixedKeys = array_map(fn($key) => $this->prefixKey((string) $key), $keysArray);

            $client->del($prefixedKeys);

            return true;
        } finally {
            $this->release($client);
        }
    }

    /**
     * Execute a callback with a borrowed Predis client.
     *
     * @param callable(PredisClient): mixed $callback
     */
    public function execute(callable $callback): mixed
    {
        $client = $this->borrow();

        try {
            return $callback($client);
        } finally {
            $this->release($client);
        }
    }

    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }

    private function borrow(): PredisClient
    {
        $client = $this->pool->pop(0.001);

        if ($client instanceof PredisClient) {
            return $client;
        }

        if ($this->created < $this->poolSize) {
            $this->created++;

            return new PredisClient($this->parameters);
        }

        return $this->pool->pop(-1);
    }

    private function release(PredisClient $client): void
    {
        $this->pool->push($client, -1);
    }

    private function buildParameters(array $redisConfig): array
    {
        $parameters = [
            'scheme' => $redisConfig['scheme'] ?? 'tcp',
            'host' => $redisConfig['host'] ?? '127.0.0.1',
            'port' => $redisConfig['port'] ?? 6379,
        ];

        $this->addIfNotNull($parameters, 'path', $redisConfig['path'] ?? null);
        $this->addIfNotNull($parameters, 'password', $redisConfig['password'] ?? null);
        $this->addIfNotNull($parameters, 'database', $redisConfig['database'] ?? null);
        $this->addIfNotNull($parameters, 'timeout', $redisConfig['timeout'] ?? null);
        $this->addIfNotNull($parameters, 'read_write_timeout', $redisConfig['read_write_timeout'] ?? null);
        $this->addIfNotNull($parameters, 'persistent', $redisConfig['persistent'] ?? null);

        $retryInterval = $redisConfig['retry_interval'] ?? 0;
        if ($retryInterval > 0) {
            $parameters['retry_interval'] = $retryInterval;
        }

        $ssl = $redisConfig['ssl'] ?? [];
        if (is_array($ssl) && ($ssl['enabled'] ?? false)) {
            $parameters['ssl'] = array_filter($ssl);
        }

        return $parameters;
    }

    private function addIfNotNull(array &$parameters, string $key, mixed $value): void
    {
        if ($value !== null && $value !== '') {
            $parameters[$key] = $value;
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
