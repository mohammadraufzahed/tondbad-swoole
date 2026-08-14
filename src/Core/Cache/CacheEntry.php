<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

class CacheEntry
{
    /**
     * @param list<string> $tags
     * @param array<string, int> $tagVersions
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $key,
        public mixed $value = null,
        public int $createdAt = 0,
        public int $expiresAt = 0,
        public int $refreshAt = 0,
        public array $tags = [],
        public array $tagVersions = [],
        public int $weight = 1,
        public array $metadata = [],
    ) {
    }
}
