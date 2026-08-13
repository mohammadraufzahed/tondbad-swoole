<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Postgres;

use PDO;
use TondbadSwoole\Database\PoolInterface;

/**
 * A per-checkout factory for the OpenSwoole native PostgreSQL client.
 *
 * The native PostgreSQL client is bound to the coroutine that creates it, so it
 * cannot be safely shared through a channel-based pool. This pool creates a new
 * PDO adapter on every checkout and releases the coroutine-bound socket when it
 * is returned, avoiding cross-coroutine reuse.
 */
class PostgresContextPool implements PoolInterface
{
    private ?PDO $fallbackPdo = null;

    public function __construct(
        private readonly \Closure $factory,
        private readonly string $name,
    ) {
    }

    public function get(): PDO
    {
        if (!$this->inCoroutine()) {
            return $this->fallbackPdo ??= $this->createPdo();
        }

        return $this->createPdo();
    }

    public function put(mixed $resource): void
    {
        if ($resource instanceof SwoolePostgresPdo) {
            $resource->release();
        }

        if ($resource === $this->fallbackPdo) {
            // Non-coroutine fallback is reused; do not close it on put.
            return;
        }
    }

    public function close(): void
    {
        if ($this->fallbackPdo instanceof SwoolePostgresPdo) {
            $this->fallbackPdo->release();
        }

        $this->fallbackPdo = null;
    }

    public function stats(): array
    {
        return [
            'total' => 0,
            'available' => 0,
            'borrowed' => 0,
            'max' => 1,
            'min' => 0,
        ];
    }

    private function createPdo(): PDO
    {
        $pdo = ($this->factory)();

        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('PostgreSQL context pool factory did not return a PDO instance.');
        }

        return $pdo;
    }

    private function inCoroutine(): bool
    {
        return class_exists(\OpenSwoole\Coroutine::class)
            && \OpenSwoole\Coroutine::getCid() >= 0;
    }
}
