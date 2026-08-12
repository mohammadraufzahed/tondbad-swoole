<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use RuntimeException;

class OptimisticLockException extends RuntimeException
{
    public static function fromEntity(Model $entity): self
    {
        return new self('The optimistic lock failed for entity [' . $entity::class . '] with key [' . json_encode($entity->getKey()) . '].');
    }
}
