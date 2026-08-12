<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use InvalidArgumentException;

class EntityManager implements EntityManagerInterface
{
    private readonly UnitOfWork $unitOfWork;

    private readonly IdentityMap $identityMap;

    private readonly EntityEventManager $eventManager;

    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {
        $this->identityMap = new IdentityMap();
        $this->eventManager = new EntityEventManager();
        $this->unitOfWork = new UnitOfWork($this, $this->identityMap, $this->eventManager);
    }

    public function persist(object $entity): self
    {
        $this->assertModel($entity);
        $this->unitOfWork->persist($entity);

        return $this;
    }

    public function remove(object $entity): self
    {
        $this->assertModel($entity);
        $this->unitOfWork->remove($entity);

        return $this;
    }

    public function flush(): void
    {
        $this->connection->transaction(fn () => $this->unitOfWork->flush());
    }

    public function clear(?object $entity = null): self
    {
        $this->unitOfWork->clear($entity);

        return $this;
    }

    public function find(string $class, mixed $id, array $populate = []): ?object
    {
        $this->assertModelClass($class);

        $entity = $this->identityMap->get($class, $id);

        if ($entity !== null) {
            return $entity;
        }

        $builder = $this->buildFindQuery($class, $id);

        if ($populate !== []) {
            $builder->with($populate);
        }

        $entity = $builder->first();

        if ($entity !== null) {
            $this->unitOfWork->persist($entity);
        }

        return $entity;
    }

    public function getReference(string $class, mixed $id): ?object
    {
        $this->assertModelClass($class);

        $entity = $this->identityMap->get($class, $id);

        return new Reference($this, $class, $id, $entity ?? null);
    }

    public function contains(object $entity): bool
    {
        return $this->unitOfWork->contains($entity);
    }

    public function getManaged(string $class, mixed $id): ?object
    {
        return $this->identityMap->get($class, $id);
    }

    public function getUnitOfWork(): UnitOfWorkInterface
    {
        return $this->unitOfWork;
    }

    public function getEventManager(): EntityEventManager
    {
        return $this->eventManager;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    private function assertModel(object $entity): void
    {
        if (!$entity instanceof Model) {
            throw new InvalidArgumentException('EntityManager only supports Model entities.');
        }
    }

    private function buildFindQuery(string $class, mixed $id): ModelBuilder
    {
        $instance = new $class();

        return (new ModelBuilder($this->connection, $this->connection->getGrammar()))
            ->from($instance->getTable())
            ->setModel($class)
            ->setEntityManager($this)
            ->where($instance->getKeyName(), '=', $id);
    }

    private function assertModelClass(string $class): void
    {
        if (!is_subclass_of($class, Model::class) && $class !== Model::class) {
            throw new InvalidArgumentException("Class [{$class}] must extend " . Model::class . '.');
        }
    }
}
