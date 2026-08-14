<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

class InMemoryTagManager implements TagManager
{
    /**
     * @var array<string, int>
     */
    private array $versions = [];

    /**
     * @param list<string> $tags
     *
     * @return array<string, int>
     */
    public function getVersions(array $tags): array
    {
        $versions = [];

        foreach ($tags as $tag) {
            $versions[$tag] = $this->versions[$tag] ?? 0;
        }

        return $versions;
    }

    /**
     * @param list<string> $tags
     */
    public function invalidate(array $tags): void
    {
        foreach ($tags as $tag) {
            $this->versions[$tag] = ($this->versions[$tag] ?? 0) + 1;
        }
    }
}
