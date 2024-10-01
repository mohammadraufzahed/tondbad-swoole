<?php

namespace TondbadSwoole\Core\Cache;

use TondbadSwoole\Core\Cache\Contracts\CacheInterface;
use Predis\Client as PredisClient;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use TondbadSwoole\Core\Config;

class PredisCache implements CacheInterface
{
    /**
     * @var PredisClient
     */
    private PredisClient $client;

    /**
     * @var SerializerInterface
     */
    private SerializerInterface $serializer;

    /**
     * Constructor
     *
     * @param array $config Predis client configuration.
     * @param SerializerInterface|null $serializer Optional serializer. If null, a default JSON serializer is used.
     */
    public function __construct()
    {
        $this->client = new PredisClient(Config::get('cache.redis', []));
        $this->client->connect();
        echo $this->client->isConnected() ? 'Connected' : 'Not Connected';
        $this->serializer = $serializer ?? new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key): mixed
    {
        $value = $this->client->get($key);
        if ($value === null) {
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
            return $this->client->setex($key, $ttl, $serializedValue) === 'OK';
        }

        return $this->client->set($key, $serializedValue) === 'OK';
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): bool
    {
        return $this->client->del([$key]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): bool
    {
        // WARNING: Using FLUSHDB will remove all keys from the current database.
        // Use with caution. Alternatively, implement a safer key iteration and deletion.

        try {
            $this->client->flushdb();
            return true;
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
        return $this->client->exists([$key]) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMultiple(iterable $keys): array
    {
        $keysArray = is_array($keys) ? $keys : iterator_to_array($keys);
        $values = $this->client->mget($keysArray);

        $results = [];
        foreach ($keysArray as $index => $key) {
            $value = $values[$index];
            $results[$key] = $value !== null ? $this->serializer->decode($value, 'json') : null;
        }

        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function setMultiple(iterable $items, ?int $ttl = null): bool
    {
        $pipeline = $this->client->pipeline();

        foreach ($items as $key => $value) {
            try {
                $serializedValue = $this->serializer->encode($value, 'json');
            } catch (\Exception $e) {
                // Handle serialization errors (e.g., log the error)
                return false;
            }

            if ($ttl !== null) {
                $pipeline->setex($key, $ttl, $serializedValue);
            } else {
                $pipeline->set($key, $serializedValue);
            }
        }

        $responses = $pipeline->execute();

        foreach ($responses as $response) {
            if ($response !== 'OK') {
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
        return $this->client->del($keysArray) > 0;
    }
}