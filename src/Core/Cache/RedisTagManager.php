<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

class RedisTagManager implements TagManager
{
    public function __construct(
        private readonly RedisCache $cache,
        private readonly string $prefix = 'tag:',
    ) {
    }

    /**
     * @param list<string> $tags
     *
     * @return array<string, int>
     */
    public function getVersions(array $tags): array
    {
        if ($tags === []) {
            return [];
        }

        $keys = array_map(fn($tag) => $this->prefixKey($tag), $tags);

        return $this->cache->execute(function ($client) use ($tags, $keys) {
            $values = $client->mget($keys);

            $versions = [];
            foreach ($tags as $index => $tag) {
                $value = $values[$index];
                $versions[$tag] = $value === null ? 0 : (int) $value;
            }

            return $versions;
        });
    }

    /**
     * @param list<string> $tags
     */
    public function invalidate(array $tags): void
    {
        if ($tags === []) {
            return;
        }

        $this->cache->execute(function ($client) use ($tags) {
            $pipeline = $client->pipeline();

            foreach ($tags as $tag) {
                $pipeline->incr($this->prefixKey($tag));
            }

            $pipeline->execute();
        });
    }

    private function prefixKey(string $tag): string
    {
        return $this->prefix . $tag;
    }
}
