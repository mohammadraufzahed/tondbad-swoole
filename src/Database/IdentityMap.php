<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use InvalidArgumentException;

class IdentityMap
{
    /** @var array<string, object> */
    private array $map = [];

    public function add(object $entity): void
    {
        $key = $this->keyFor($entity);

        if ($key === null) {
            return;
        }

        $this->map[$key] = $entity;
    }

    public function get(string $class, mixed $id): ?object
    {
        $key = $this->key($class, $id);

        return $this->map[$key] ?? null;
    }

    public function has(string $class, mixed $id): bool
    {
        return $this->get($class, $id) !== null;
    }

    public function remove(object $entity): void
    {
        $key = $this->keyFor($entity);

        if ($key !== null) {
            unset($this->map[$key]);
        }
    }

    public function clear(): void
    {
        $this->map = [];
    }

    private function keyFor(object $entity): ?string
    {
        if (!$entity instanceof Model) {
            throw new InvalidArgumentException('IdentityMap only supports Model entities.');
        }

        $id = $entity->getKey();

        if ($id === null || $id === '') {
            return null;
        }

        return $this->key($entity::class, $id);
    }

    private function key(string $class, mixed $id): string
    {
        return $class . ':' . $this->normalizeId($id);
    }

    private function normalizeId(mixed $id): string
    {
        if (is_array($id)) {
            ksort($id);

            return json_encode($id) ?: '';
        }

        return (string) $id;
    }
}
