<?php

declare(strict_types=1);

namespace TondbadSwoole\View\Live;

use TondbadSwoole\Contracts\CacheContract;

final class StateStore
{
    private string $prefix;
    private int $ttl;

    public function __construct(private readonly CacheContract $cache, string $prefix = 't:live:state:', int $ttl = 3600)
    {
        $this->prefix = rtrim($prefix, ':');
        $this->ttl = $ttl;
    }

    /**
     * @param array<string, mixed> $state
     */
    public function save(array $state): string
    {
        $token = $this->generateToken();
        $this->cache->set($this->prefix . ':' . $token, $state, $this->ttl);

        return $token;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $token): ?array
    {
        return $this->cache->get($this->prefix . ':' . $token);
    }

    public function delete(string $token): void
    {
        $this->cache->delete($this->prefix . ':' . $token);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
