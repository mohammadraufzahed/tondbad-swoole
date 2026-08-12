<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

class EntityRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $entityClass,
    ) {
    }

    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    public function find(mixed $id, array $populate = []): ?Model
    {
        $entity = $this->entityManager->find($this->entityClass, $id, $populate);

        return $entity instanceof Model ? $entity : null;
    }

    public function findAll(): array
    {
        return $this->createQueryBuilder()->get();
    }

    /**
     * @param array<string, mixed> $criteria
     * @param array<string, string> $orderBy
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $query = $this->createQueryBuilder();

        foreach ($criteria as $column => $value) {
            $query->where($column, '=', $value);
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        if ($offset !== null) {
            $query->offset($offset);
        }

        return $query->get();
    }

    /**
     * @param array<string, mixed> $criteria
     */
    public function findOneBy(array $criteria): ?Model
    {
        $result = $this->findBy($criteria, [], 1);

        return $result[0] ?? null;
    }

    public function createQueryBuilder(): ModelBuilder
    {
        $instance = new $this->entityClass();

        return $instance->newQuery();
    }
}
