<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use PDO;
use PDOStatement;
use Throwable;
use TondbadSwoole\Database\Query\Builder;
use TondbadSwoole\Database\Query\Grammar;

class PdoConnection implements ConnectionInterface
{
    private ?PDO $transactionPdo = null;

    private ?PDO $queryPdo = null;

    public function __construct(
        private readonly PoolInterface $pool,
        private readonly Grammar $grammar,
        private readonly string $name,
    ) {
    }

    public function table(string $table, ?string $as = null): Builder
    {
        return $this->query()->table($table, $as);
    }

    public function query(): Builder
    {
        return new Builder($this, $this->grammar);
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings, function (PDOStatement $statement): array {
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        });
    }

    public function insert(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings, function (PDOStatement $statement): bool {
            return $statement->rowCount() > 0;
        });
    }

    public function update(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    public function delete(string $sql, array $bindings = []): int
    {
        return $this->affectingStatement($sql, $bindings);
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->run($sql, $bindings, function (PDOStatement $statement): bool {
            return $statement->rowCount() >= 0;
        });
    }

    public function lastInsertId(?string $sequence = null): int|string
    {
        $pdo = $this->getPdo();

        try {
            return $pdo->lastInsertId($sequence);
        } finally {
            $this->putPdo($pdo);
        }
    }

    public function transaction(callable $callback, int $attempts = 1): mixed
    {
        $attempt = 0;

        while ($attempt < $attempts) {
            $attempt++;

            $pdo = $this->pool->get();
            $this->transactionPdo = $pdo;

            try {
                $pdo->beginTransaction();
                $result = $callback($this);
                $pdo->commit();

                return $result;
            } catch (Throwable $e) {
                $pdo->rollBack();

                if ($attempt >= $attempts) {
                    throw $e;
                }
            } finally {
                $this->transactionPdo = null;
                $this->pool->put($pdo);
            }
        }

        return null;
    }

    public function getGrammar(): Grammar
    {
        return $this->grammar;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPdo(): PDO
    {
        if ($this->transactionPdo !== null) {
            return $this->transactionPdo;
        }

        $this->queryPdo = $this->pool->get();

        return $this->queryPdo;
    }

    public function putPdo(PDO $pdo): void
    {
        if ($this->transactionPdo !== null || $this->queryPdo === null || $this->queryPdo !== $pdo) {
            return;
        }

        $this->pool->put($pdo);
        $this->queryPdo = null;
    }

    protected function run(string $sql, array $bindings, callable $callback): mixed
    {
        $pdo = $this->getPdo();

        try {
            $statement = $pdo->prepare($sql);
            $statement->execute($bindings);

            return $callback($statement);
        } catch (Throwable $e) {
            throw $e;
        } finally {
            $this->putPdo($pdo);
        }
    }

    protected function affectingStatement(string $sql, array $bindings): int
    {
        return $this->run($sql, $bindings, function (PDOStatement $statement): int {
            return $statement->rowCount();
        });
    }

}
