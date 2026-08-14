<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

interface TagManager
{
    /**
     * Return the current version for each tag.
     *
     * @param list<string> $tags
     *
     * @return array<string, int>
     */
    public function getVersions(array $tags): array;

    /**
     * Increment the version for each tag, invalidating existing entries.
     *
     * @param list<string> $tags
     */
    public function invalidate(array $tags): void;
}
