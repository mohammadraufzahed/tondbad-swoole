<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

interface Serializer
{
    public function serialize(mixed $value): string;

    public function deserialize(string $data): mixed;
}
