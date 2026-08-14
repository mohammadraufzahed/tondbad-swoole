<?php

declare(strict_types=1);

namespace TondbadSwoole\Core\Cache;

class JsonSerializer implements Serializer
{
    public function __construct(
        private readonly bool $associative = true,
    ) {
    }

    public function serialize(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function deserialize(string $data): mixed
    {
        return json_decode($data, $this->associative, 512, JSON_THROW_ON_ERROR);
    }
}
