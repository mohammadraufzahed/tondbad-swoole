<?php

declare(strict_types=1);

namespace TondbadSwoole\Support;

use TondbadSwoole\Contracts\ContextInterface;

class Context implements ContextInterface
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $cid = $this->cid();

        return $this->storage[$cid][$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $cid = $this->cid();

        $this->storage[$cid][$key] = $value;
    }

    public function delete(string $key): void
    {
        $cid = $this->cid();

        unset($this->storage[$cid][$key]);
    }

    public function has(string $key): bool
    {
        $cid = $this->cid();

        return isset($this->storage[$cid][$key]);
    }

    public function clear(): void
    {
        $cid = $this->cid();

        unset($this->storage[$cid]);
    }

    public function clearAll(): void
    {
        $this->storage = [];
    }

    private function cid(): int
    {
        if (class_exists(\OpenSwoole\Coroutine::class)) {
            return \OpenSwoole\Coroutine::getCid();
        }

        return 0;
    }
}
