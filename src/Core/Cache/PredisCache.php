<?php

namespace TondbadSwoole\Core\Cache;

use TondbadSwoole\Core\Cache\Contracts\CacheInterface;
use Predis\Client as PredisClient;
use Predis\Collection\Iterator\Keyspace;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use TondbadSwoole\Core\Config;

class PredisCache implements CacheInterface
{
    private PredisClient $client;
    private SerializerInterface $serializer;
    private string $prefix;

    public function __construct(?SerializerInterface $serializer = null)
    {
        $this->client = new PredisClient(Config::get('cache.redis', []));
        $this->client->connect();
        $this->prefix = Config::get('cache.redis.options.prefix', '');
        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    public function get(string $key): mixed
    {
        $value = $this->client->get($this->prefixKey($key));
        if ($value === null) {
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
            return (string) $this->client->setex($prefixedKey, $ttl, $serializedValue) === 'OK';
        }

        return (string) $this->client->set($prefixedKey, $serializedValue) === 'OK';
    }

    public function delete(string $key): bool
    {
        return $this->client->del([$this->prefixKey($key)]) > 0;
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

        $values = $this->client->mget($prefixedKeys);

        $results = [];
        foreach ($prefixedKeys as $index => $pk) {
            $value = $values[$index];
            $results[$keyMap[$pk]] = $value !== null ? $this->serializer->decode($value, 'json') : null;
        }

        return $results;
    }

    public function setMultiple(iterable $items, ?int $ttl = null): bool
    {
        $pipeline = $this->client->pipeline();

        foreach ($items as $key => $value) {
            try {
                $serializedValue = $this->serializer->encode($value, 'json');
            } catch (\Exception $e) {
                return false;
            }

            $prefixedKey = $this->prefixKey((string) $key);

            if ($ttl !== null) {
                $pipeline->setex($prefixedKey, $ttl, $serializedValue);
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
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys);
        $prefixedKeys = array_map(fn($key) => $this->prefixKey((string) $key), $keysArray);
        return $this->client->del($prefixedKeys) > 0;
    }

    private function prefixKey(string $key): string
    {
        return $this->prefix . $key;
    }
}
