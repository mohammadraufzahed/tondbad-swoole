<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use OpenSwoole\Coroutine\Channel;
use PDO;
use RuntimeException;

class SwoolePdoPool implements PoolInterface
{
    private Channel $channel;

    private int $current = 0;

    private float $waitTimeout;

    public function __construct(
        private readonly \Closure $factory,
        private readonly int $min = 0,
        private readonly int $max = 10,
        float $waitTimeout = 3.0,
    ) {
        $this->waitTimeout = max($waitTimeout, 0.0);
        $this->channel = new Channel($max);
    }

    public function get(): PDO
    {
        $pdo = $this->channel->pop($this->waitTimeout);

        if ($pdo !== false && $pdo instanceof PDO) {
            return $pdo;
        }

        if ($this->current < $this->max) {
            return $this->createPdo();
        }

        throw new RuntimeException('Unable to get PDO from pool: timeout.');
    }

    public function put(mixed $resource): void
    {
        if (!$resource instanceof PDO) {
            return;
        }

        $result = $this->channel->push($resource, $this->waitTimeout);

        if ($result === false) {
            $this->close($resource);
        }
    }

    private function createPdo(): PDO
    {
        $pdo = ($this->factory)();

        if (!$pdo instanceof PDO) {
            throw new RuntimeException('PDO pool factory did not return a PDO instance.');
        }

        $this->current++;

        return $pdo;
    }

    private function close(PDO $pdo): void
    {
        $this->current = max(0, $this->current - 1);
    }
}
