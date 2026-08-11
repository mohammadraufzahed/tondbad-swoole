<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use PDO;
use RuntimeException;

class SimplePdoPool implements PoolInterface
{
    private ?PDO $pdo = null;

    private int $borrowed = 0;

    public function __construct(private readonly \Closure $factory)
    {
    }

    public function get(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = ($this->factory)();
        }

        if (!$this->pdo instanceof PDO) {
            throw new RuntimeException('PDO pool factory did not return a PDO instance.');
        }

        $this->borrowed++;

        return $this->pdo;
    }

    public function put(mixed $resource): void
    {
        $this->borrowed = max(0, $this->borrowed - 1);
    }
}
