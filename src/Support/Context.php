<?php

declare(strict_types=1);

namespace TondbadSwoole\Support;

use TondbadSwoole\Contracts\ContextInterface;

class Context implements ContextInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $fallback = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $context = $this->context();

        return $context[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        if ($this->inCoroutine()) {
            $context = \OpenSwoole\Coroutine::getContext();
            $context[$key] = $value;

            return;
        }

        $this->fallback[$key] = $value;
    }

    public function delete(string $key): void
    {
        if ($this->inCoroutine()) {
            $context = \OpenSwoole\Coroutine::getContext();
            unset($context[$key]);

            return;
        }

        unset($this->fallback[$key]);
    }

    public function has(string $key): bool
    {
        $context = $this->context();

        return isset($context[$key]);
    }

    public function clear(): void
    {
        if ($this->inCoroutine()) {
            $context = \OpenSwoole\Coroutine::getContext();

            foreach ($this->keys($context) as $key) {
                unset($context[$key]);
            }

            return;
        }

        $this->fallback = [];
    }

    public function clearAll(): void
    {
        $this->fallback = [];

        if ($this->inCoroutine()) {
            $context = \OpenSwoole\Coroutine::getContext();

            foreach ($this->keys($context) as $key) {
                unset($context[$key]);
            }
        }
    }

    /**
     * @return \OpenSwoole\Coroutine\Context|array<string, mixed>
     */
    private function context(): \OpenSwoole\Coroutine\Context|array
    {
        if ($this->inCoroutine()) {
            return \OpenSwoole\Coroutine::getContext();
        }

        return $this->fallback;
    }

    private function inCoroutine(): bool
    {
        return class_exists(\OpenSwoole\Coroutine::class)
            && \OpenSwoole\Coroutine::getCid() >= 0;
    }

    /**
     * @param \OpenSwoole\Coroutine\Context|array<string, mixed> $context
     * @return array<int, string>
     */
    private function keys(\OpenSwoole\Coroutine\Context|array $context): array
    {
        $keys = [];

        foreach ($context as $key => $value) {
            $keys[] = (string) $key;
        }

        return $keys;
    }
}
