<?php

namespace TondbadSwoole\Core\Cache\Contracts;
/**
 * Interface Cache
 *
 * Defines a basic caching mechanism.
 */
interface CacheInterface
{
    /**
     * Retrieve an item from the cache by its unique key.
     *
     * @param string $key The unique identifier for the cached item.
     * @return mixed The cached value or null if not found.
     */
    public function get(string $key): mixed;

    /**
     * Store an item in the cache.
     *
     * @param string   $key   The unique identifier for the cached item.
     * @param mixed    $value The value to be cached.
     * @param int|null $ttl   Optional. The time-to-live in seconds. Null for no expiration.
     * @return bool True on success, false on failure.
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique identifier for the cached item.
     * @return bool True on success, false on failure.
     */
    public function delete(string $key): bool;

    /**
     * Clear all items from the cache.
     *
     * @return bool True on success, false on failure.
     */
    public function clear(): bool;

    /**
     * Determine whether an item exists in the cache.
     *
     * @param string $key The unique identifier for the cached item.
     * @return bool True if the item exists, false otherwise.
     */
    public function has(string $key): bool;

    /**
     * Retrieve multiple items from the cache.
     *
     * @param iterable $keys An iterable of keys to retrieve.
     * @return array An associative array of key => value pairs. Missing keys will have null as their value.
     */
    public function getMultiple(iterable $keys): array;

    /**
     * Store multiple items in the cache.
     *
     * @param iterable    $items An associative array of key => value pairs to store.
     * @param int|null    $ttl   Optional. The time-to-live in seconds. Null for no expiration.
     * @return bool True on success for all items, false otherwise.
     */
    public function setMultiple(iterable $items, ?int $ttl = null): bool;

    /**
     * Delete multiple items from the cache.
     *
     * @param iterable $keys An iterable of keys to delete.
     * @return bool True on success for all keys, false otherwise.
     */
    public function deleteMultiple(iterable $keys): bool;
}