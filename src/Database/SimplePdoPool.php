<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use PDO;
use PDOException;
use RuntimeException;

class SimplePdoPool implements PoolInterface
{
    private ?PooledConnection $wrapper = null;

    private int $borrowed = 0;

    private float $maxAge;

    private int $maxUsage;

    private bool $healthCheck;

    private float $checkInterval;

    private bool $closed = false;

    public function __construct(
        private readonly \Closure $factory,
        private readonly array $config = [],
    ) {
        $this->maxAge = (float) ($config['max_age'] ?? 0.0);
        $this->maxUsage = (int) ($config['max_usage'] ?? 0);
        $this->healthCheck = (bool) ($config['health_check'] ?? false);
        $this->checkInterval = (float) ($config['check_interval'] ?? 30.0);
    }

    public function get(): PDO
    {
        if ($this->closed) {
            throw new RuntimeException('Unable to get PDO from pool: pool is closed.');
        }

        if ($this->wrapper === null || !$this->wrapper->isOpen() || $this->shouldDiscard($this->wrapper)) {
            if ($this->wrapper instanceof PooledConnection) {
                $this->discard($this->wrapper);
            }

            $this->wrapper = $this->createWrapper();
        }

        $this->wrapper->lastHealthCheck = microtime(true);
        $this->borrowed++;

        return $this->wrapper->pdo;
    }

    public function put(mixed $resource): void
    {
        if (!$resource instanceof PDO) {
            return;
        }

        if (!$this->wrapper instanceof PooledConnection || $this->wrapper->pdo !== $resource) {
            return;
        }

        $this->borrowed = max(0, $this->borrowed - 1);

        if ($this->borrowed > 0) {
            return;
        }

        $this->wrapper->usage++;

        if ($this->closed || !$this->wrapper->isOpen() || $this->shouldRetire($this->wrapper)) {
            $this->discard($this->wrapper);
            $this->wrapper = null;
        }
    }

    public function close(): void
    {
        $this->closed = true;

        if ($this->wrapper instanceof PooledConnection) {
            $this->wrapper->close();
            $this->wrapper = null;
        }

        $this->borrowed = 0;
    }

    public function stats(): array
    {
        return [
            'total' => $this->wrapper instanceof PooledConnection ? 1 : 0,
            'available' => ($this->wrapper instanceof PooledConnection && $this->borrowed === 0) ? 1 : 0,
            'borrowed' => $this->borrowed,
            'max' => 1,
            'min' => 0,
        ];
    }

    private function createWrapper(): PooledConnection
    {
        $pdo = ($this->factory)();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('PDO pool factory did not return a PDO instance.');
        }

        return new PooledConnection($pdo, microtime(true));
    }

    private function discard(PooledConnection $wrapper): void
    {
        $wrapper->close();
    }

    private function shouldDiscard(PooledConnection $wrapper): bool
    {
        if ($this->maxAge > 0.0 && $wrapper->age() > $this->maxAge) {
            return true;
        }

        if (!$this->healthCheck) {
            return false;
        }

        if (microtime(true) - $wrapper->lastHealthCheck <= $this->checkInterval) {
            return false;
        }

        try {
            $wrapper->pdo->query($this->getHealthCheckSql());

            return false;
        } catch (PDOException) {
            return true;
        }
    }

    private function shouldRetire(PooledConnection $wrapper): bool
    {
        return ($this->maxUsage > 0 && $wrapper->usage >= $this->maxUsage)
            || ($this->maxAge > 0.0 && $wrapper->age() > $this->maxAge);
    }

    private function getHealthCheckSql(): string
    {
        return 'SELECT 1';
    }
}
