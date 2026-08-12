<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use PDO;

class PooledConnection
{
    public float $lastHealthCheck;

    public int $usage = 0;

    public function __construct(
        public ?PDO $pdo,
        public readonly float $createdAt,
    ) {
        $this->lastHealthCheck = $createdAt;
    }

    public function close(): void
    {
        $this->pdo = null;
    }

    public function age(): float
    {
        return microtime(true) - $this->createdAt;
    }

    public function isOpen(): bool
    {
        return $this->pdo instanceof PDO;
    }
}
