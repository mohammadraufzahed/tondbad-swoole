<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

use DateInterval;
use Predis\Client as PredisClient;
use Predis\Collection\Iterator\Keyspace;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use TondbadSwoole\Contracts\CacheInterface;
use TondbadSwoole\Core\Config;

class PredisCache implements CacheInterface
{
    private PredisClient $client;
    private SerializerInterface $serializer;
    private string $prefix;

    public function __construct(
        private readonly Config $config,
        ?SerializerInterface $serializer = null
    ) {
        $redisConfig = $config->get('cache.redis', []);
        $this->client = new PredisClient($redisConfig);
        $this->client->connect();
        $this->prefix = $redisConfig['options']['prefix'] ?? '';
        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->client->get($this->prefixKey($key));

        if ($value === null) {
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
            $seconds = $this->ttlToSeconds($ttl);

            if ($seconds <= 0) {
                return $this->delete($key);
            }

            return (string) $this->client->setex($prefixedKey, $seconds, $serializedValue) === 'OK';
        }

        return (string) $this->client->set($prefixedKey, $serializedValue) === 'OK';
    }

    public function delete(string $key): bool
    {
        $this->client->del([$this->prefixKey($key)]);

        return true;
    }

    public function clear(): bool
    {
        if ($this->prefix === '') {
            try {
                $this->client->flushdb();

                return true;
            } catch (\Exception $e) {
                return false;
            }
        }

        try {
            $batch = [];

            foreach (new Keyspace($this->client, $this->prefix . '*') as $key) {
                $batch[] = $key;

                if (count($batch) >= 100) {
                    $this->client->del($batch);
                    $batch = [];
                }
            }

            if (!empty($batch)) {
                $this->client->del($batch);
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function has(string $key): bool
    {
        return $this->client->exists([$this->prefixKey($key)]) > 0;
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

        $values = $this->client->mget($prefixedKeys);

        $results = [];
        foreach ($prefixedKeys as $index => $pk) {
            $value = $values[$index];
            $results[$keyMap[$pk]] = $value !== null ? $this->serializer->decode($value, 'json') : $default;
        }

        return $results;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $pipeline = $this->client->pipeline();

        foreach ($values as $key => $value) {
            try {
                $serializedValue = $this->serializer->encode($value, 'json');
            } catch (\Exception $e) {
                return false;
            }

            $prefixedKey = $this->prefixKey((string) $key);

            if ($ttl !== null) {
                $pipeline->setex($prefixedKey, $this->ttlToSeconds($ttl), $serializedValue);
            } else {
                $pipeline->set($prefixedKey, $serializedValue);
            }
        }

        $responses = $pipeline->execute();

        foreach ($responses as $response) {
            if ((string) $response !== 'OK') {
                return false;
            }
        }

        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys, false);
        $prefixedKeys = array_map(fn($key) => $this->prefixKey((string) $key), $keysArray);

        $this->client->del($prefixedKeys);

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
