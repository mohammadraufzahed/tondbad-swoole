<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use OpenSwoole\Coroutine\Channel;
use PDO;
use PDOException;
use RuntimeException;

class SwoolePdoPool implements PoolInterface
{
    private Channel $channel;

    private int $current = 0;

    private int $available = 0;

    private float $waitTimeout;

    private float $maxAge;

    private int $maxUsage;

    private bool $healthCheck;

    private float $checkInterval;

    private bool $closed = false;

    /**
     * @var array<int, PooledConnection>
     */
    private array $borrowed = [];

    public function __construct(
        private readonly \Closure $factory,
        private readonly int $min = 0,
        private readonly int $max = 10,
        float $waitTimeout = 3.0,
        private readonly array $config = [],
    ) {
        $this->waitTimeout = max($waitTimeout, 0.0);
        $this->maxAge = (float) ($config['max_age'] ?? 0.0);
        $this->maxUsage = (int) ($config['max_usage'] ?? 0);
        $this->healthCheck = (bool) ($config['health_check'] ?? false);
        $this->checkInterval = (float) ($config['check_interval'] ?? 30.0);
        $this->channel = new Channel($max);
    }

    public function get(): PDO
    {
        if ($this->closed) {
            throw new RuntimeException('Unable to get PDO from pool: pool is closed.');
        }

        $wrapper = $this->channel->pop($this->waitTimeout);

        if ($wrapper instanceof PooledConnection) {
            $this->available--;

            if ($this->shouldDiscard($wrapper)) {
                $this->discard($wrapper);

                return $this->get();
            }

            $wrapper->lastHealthCheck = microtime(true);
            $this->borrowed[spl_object_id($wrapper->pdo)] = $wrapper;

            return $wrapper->pdo;
        }

        if ($this->current < $this->max) {
            $wrapper = $this->createWrapper();
            $this->borrowed[spl_object_id($wrapper->pdo)] = $wrapper;

            return $wrapper->pdo;
        }

        throw new RuntimeException('Unable to get PDO from pool: timeout.');
    }

    public function put(mixed $resource): void
    {
        if (!$resource instanceof PDO) {
            return;
        }

        $id = spl_object_id($resource);
        $wrapper = $this->borrowed[$id] ?? null;

        if (!$wrapper instanceof PooledConnection) {
            return;
        }

        unset($this->borrowed[$id]);

        if ($this->closed || !$wrapper->isOpen()) {
            $this->discard($wrapper);

            return;
        }

        $wrapper->usage++;

        if ($this->shouldRetire($wrapper)) {
            $this->discard($wrapper);

            return;
        }

        $this->push($wrapper);
    }

    public function close(): void
    {
        $this->closed = true;

        foreach ($this->borrowed as $wrapper) {
            $wrapper->close();
        }

        $this->borrowed = [];
        $this->current = 0;

        while (($wrapper = $this->channel->pop(0.0)) instanceof PooledConnection) {
            $wrapper->close();
        }

        $this->available = 0;
        $this->channel->close();
    }

    public function stats(): array
    {
        return [
            'total' => $this->current,
            'available' => $this->available,
            'borrowed' => count($this->borrowed),
            'max' => $this->max,
            'min' => $this->min,
        ];
    }

    private function createWrapper(): PooledConnection
    {
        $pdo = ($this->factory)();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('PDO pool factory did not return a PDO instance.');
        }

        $this->current++;

        return new PooledConnection($pdo, microtime(true));
    }

    private function push(PooledConnection $wrapper): void
    {
        $result = $this->channel->push($wrapper, $this->waitTimeout);

        if ($result === false) {
            $this->discard($wrapper);

            return;
        }

        $this->available++;
    }

    private function discard(PooledConnection $wrapper): void
    {
        $wrapper->close();
        $this->current = max(0, $this->current - 1);
    }

    private function shouldDiscard(PooledConnection $wrapper): bool
    {
        if (!$wrapper->isOpen()) {
            return true;
        }

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
