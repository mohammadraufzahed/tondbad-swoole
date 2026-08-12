<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

interface EntityManagerInterface
{
    public function persist(object $entity): self;

    public function remove(object $entity): self;

    public function flush(): void;

    public function clear(?object $entity = null): self;

    public function find(string $class, mixed $id, array $populate = []): ?object;

    public function getReference(string $class, mixed $id): ?object;

    public function contains(object $entity): bool;

    public function getManaged(string $class, mixed $id): ?object;

    public function getUnitOfWork(): UnitOfWorkInterface;

    public function getEventManager(): EntityEventManager;

    public function getConnection(): ConnectionInterface;

    public function getRepository(string $class): EntityRepository;
}
