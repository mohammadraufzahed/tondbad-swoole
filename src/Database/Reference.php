<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use RuntimeException;

class Reference
{
    private ?Model $entity = null;

    private readonly string $primaryKey;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $className,
        private readonly mixed $identifier,
        ?Model $entity = null,
    ) {
        $this->primaryKey = (new $this->className())->getKeyName();
        $this->entity = $entity;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getId(): mixed
    {
        return $this->identifier;
    }

    public function getPrimaryKey(): string
    {
        return $this->primaryKey;
    }

    public function isInitialized(): bool
    {
        return $this->entity !== null;
    }

    public function load(): Model
    {
        if ($this->entity === null) {
            $entity = $this->entityManager->find($this->className, $this->identifier);

            if ($entity === null) {
                throw new RuntimeException("Reference not found: {$this->className}#{$this->identifier}");
            }

            $this->entity = $entity;
        }

        return $this->entity;
    }

    public function getValue(): ?Model
    {
        return $this->entity;
    }

    public function __get(string $name): mixed
    {
        if ($name === $this->primaryKey) {
            return $this->identifier;
        }

        return $this->load()->$name;
    }

    public function __set(string $name, mixed $value): void
    {
        $this->load()->$name = $value;
    }

    public function __isset(string $name): bool
    {
        if ($name === $this->primaryKey) {
            return true;
        }

        return isset($this->load()->$name);
    }

    public function __unset(string $name): void
    {
        unset($this->load()->$name);
    }

    public function __call(string $method, array $arguments): mixed
    {
        $entity = $this->load();

        return $entity->$method(...$arguments);
    }

    public function __toString(): string
    {
        if (is_array($this->identifier)) {
            return json_encode($this->identifier, JSON_THROW_ON_ERROR) ?: '';
        }

        return (string) $this->identifier;
    }
}
