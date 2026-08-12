<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

interface UnitOfWorkInterface
{
    public function persist(object $entity): void;

    public function remove(object $entity): void;

    public function flush(): void;

    public function clear(?object $entity = null): void;

    public function contains(object $entity): bool;

    public function getEntityState(object $entity): string;

    public function isScheduledForInsert(object $entity): bool;

    public function isScheduledForUpdate(object $entity): bool;

    public function isScheduledForDelete(object $entity): bool;
}
