<?php

declare(strict_types=1);

namespace TondbadSwoole\Contracts\Cache;

class CacheItem
{
    /**
     * @param list<string> $tags
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $key,
        public int $lifetime = 0,
        public ?float $refreshRatio = null,
        public array $tags = [],
        public int $weight = 1,
        public array $metadata = [],
    ) {
    }

    public function lifetime(int $seconds, ?float $refreshRatio = null): self
    {
        $this->lifetime = $seconds;

        if ($refreshRatio !== null) {
            $this->refreshRatio = max(0.0, min(1.0, $refreshRatio));
        }

        return $this;
    }

    /**
     * @param string ...$tags
     */
    public function tag(string ...$tags): self
    {
        foreach ($tags as $tag) {
            if (!in_array($tag, $this->tags, true)) {
                $this->tags[] = $tag;
            }
        }

        return $this;
    }

    public function weight(int $weight): self
    {
        $this->weight = max(1, $weight);

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function expiresAt(): int
    {
        if ($this->lifetime <= 0) {
            return 0;
        }

        return time() + $this->lifetime;
    }

    public function refreshAt(): int
    {
        if ($this->lifetime <= 0 || $this->refreshRatio === null || $this->refreshRatio <= 0.0) {
            return 0;
        }

        return (int) (time() + ($this->lifetime * $this->refreshRatio));
    }
}
