<?php

declare(strict_types=1);

namespace TondbadSwoole\Scheduling;

use Closure;

class ScheduleRegistry
{
    /**
     * @var array<string, Closure>
     */
    private array $closures = [];

    public function register(string $id, Closure $closure): void
    {
        $this->closures[$id] = $closure;
    }

    public function resolve(string $id): ?Closure
    {
        return $this->closures[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return isset($this->closures[$id]);
    }
}
