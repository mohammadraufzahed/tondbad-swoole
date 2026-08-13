<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use InvalidArgumentException;
use WeakMap;

class UnitOfWork implements UnitOfWorkInterface
{
    public const STATE_NEW = 'new';
    public const STATE_MANAGED = 'managed';
    public const STATE_REMOVED = 'removed';
    public const STATE_DETACHED = 'detached';

    /** @var WeakMap<object, string> */
    private WeakMap $states;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly IdentityMap $identityMap,
        private readonly ?EntityEventManager $eventManager = null,
    ) {
        $this->states = new WeakMap();
    }

    public function persist(object $entity): void
    {
        $this->assertModel($entity);

        $current = $this->getEntityState($entity);

        switch ($current) {
            case self::STATE_NEW:
            case self::STATE_MANAGED:
                return;

            case self::STATE_REMOVED:
                $this->identityMap->add($entity);
                $this->states[$entity] = self::STATE_MANAGED;

                return;
        }

        /** @var Model $entity */
        if ($entity->exists) {
            $this->identityMap->add($entity);
            $this->states[$entity] = self::STATE_MANAGED;
            $this->eventManager?->dispatchEvent('postLoad', $entity);
        } else {
            $this->states[$entity] = self::STATE_NEW;
        }

        $this->cascade($entity, 'persist');
    }

    public function remove(object $entity): void
    {
        $this->assertModel($entity);

        $current = $this->getEntityState($entity);

        if ($current === self::STATE_NEW) {
            $this->detach($entity);

            return;
        }

        if ($current === self::STATE_MANAGED || $current === self::STATE_DETACHED) {
            $this->identityMap->remove($entity);
            $this->states[$entity] = self::STATE_REMOVED;
        }

        $this->cascade($entity, 'remove');
    }

    public function flush(): void
    {
        $entities = $this->getTrackedEntities();

        foreach ($entities as $entity) {
            $this->eventManager?->dispatchEvent('preFlush', $entity);
        }

        foreach ($entities as $entity) {
            $state = $this->states[$entity] ?? self::STATE_DETACHED;

            switch ($state) {
                case self::STATE_NEW:
                    $this->eventManager?->dispatchEvent('prePersist', $entity);
                    $this->executeInsert($entity);
                    $this->eventManager?->dispatchEvent('postPersist', $entity);
                    break;

                case self::STATE_MANAGED:
                    $this->executeUpdate($entity);
                    break;

                case self::STATE_REMOVED:
                    $this->eventManager?->dispatchEvent('preRemove', $entity);
                    $this->executeDelete($entity);
                    $this->eventManager?->dispatchEvent('postRemove', $entity);
                    break;
            }
        }

        foreach ($entities as $entity) {
            $this->eventManager?->dispatchEvent('onFlush', $entity);
        }

        $this->cleanupAfterFlush($entities);

        foreach ($entities as $entity) {
            $this->eventManager?->dispatchEvent('postFlush', $entity);
        }
    }

    public function clear(?object $entity = null): void
    {
        if ($entity === null) {
            $this->states = new WeakMap();
            $this->identityMap->clear();

            return;
        }

        $this->detach($entity);
    }

    public function contains(object $entity): bool
    {
        if (!$this->states->offsetExists($entity)) {
            return false;
        }

        $state = $this->states[$entity];

        return $state !== self::STATE_DETACHED;
    }

    public function getEntityState(object $entity): string
    {
        return $this->states[$entity] ?? self::STATE_DETACHED;
    }

    public function isScheduledForInsert(object $entity): bool
    {
        return $this->getEntityState($entity) === self::STATE_NEW;
    }

    public function isScheduledForUpdate(object $entity): bool
    {
        return $this->getEntityState($entity) === self::STATE_MANAGED;
    }

    public function isScheduledForDelete(object $entity): bool
    {
        return $this->getEntityState($entity) === self::STATE_REMOVED;
    }

    private function executeInsert(object $entity): void
    {
        /** @var Model $entity */
        $entity->performInsert();
        $this->identityMap->add($entity);
        $this->states[$entity] = self::STATE_MANAGED;
    }

    private function executeUpdate(object $entity): void
    {
        /** @var Model $entity */
        if (!$entity->isDirty()) {
            return;
        }

        $this->eventManager?->dispatchEvent('preUpdate', $entity);

        if (!$entity->isDirty()) {
            // preUpdate listener cancelled the changes
            return;
        }

        $entity->performUpdate();
        $this->states[$entity] = self::STATE_MANAGED;

        $this->eventManager?->dispatchEvent('postUpdate', $entity);
    }

    private function executeDelete(object $entity): void
    {
        /** @var Model $entity */
        $entity->performDelete();
        $this->identityMap->remove($entity);
        $this->states[$entity] = self::STATE_DETACHED;
    }

    private function detach(object $entity): void
    {
        $this->identityMap->remove($entity);
        unset($this->states[$entity]);
    }

    private function cleanupAfterFlush(array $entities): void
    {
        foreach ($entities as $entity) {
            $state = $this->states[$entity] ?? self::STATE_DETACHED;

            if ($state === self::STATE_REMOVED || $state === self::STATE_DETACHED) {
                $this->detach($entity);
                continue;
            }

            if ($state === self::STATE_NEW || $state === self::STATE_MANAGED) {
                $this->states[$entity] = self::STATE_MANAGED;
            }
        }
    }

    /** @return list<object> */
    private function getTrackedEntities(): array
    {
        $entities = [];

        foreach ($this->states as $entity => $state) {
            $entities[] = $entity;
        }

        return $entities;
    }

    private function cascade(Model $entity, string $operation): void
    {
        foreach ($entity->getRelations() as $name => $related) {
            if (!in_array($operation, $entity->getRelationCascade($name), true)) {
                continue;
            }

            foreach ($this->normalizeRelated($related) as $item) {
                if ($item instanceof Model) {
                    match ($operation) {
                        'persist' => $this->persist($item),
                        'remove' => $this->remove($item),
                    };
                }
            }
        }
    }

    /**
     * @return list<Model>
     */
    private function normalizeRelated(mixed $related): array
    {
        if ($related instanceof Model) {
            return [$related];
        }

        if (is_array($related)) {
            $models = [];

            foreach ($related as $item) {
                if ($item instanceof Model) {
                    $models[] = $item;
                }
            }

            return $models;
        }

        return [];
    }

    private function assertModel(object $entity): void
    {
        if (!$entity instanceof Model) {
            throw new InvalidArgumentException('UnitOfWork only supports Model entities.');
        }
    }
}
