<?php

declare(strict_types=1);

namespace TondbadSwoole\Database;

use PDO;
use PDOStatement;
use Throwable;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\Query\Builder;
use TondbadSwoole\Database\Query\Grammar;
use TondbadSwoole\Database\Schema\Builder as SchemaBuilder;

class PdoConnection implements ConnectionInterface
{
    public function __construct(
        private readonly PoolInterface $pool,
        private readonly Grammar $grammar,
        private readonly string $name,
        private readonly ContextInterface $context,
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
            $this->context->set($this->transactionKey(), $pdo);

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
                $this->context->delete($this->transactionKey());
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

    public function getSchemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder($this);
    }

    public function getPdo(): PDO
    {
        $transactionPdo = $this->context->get($this->transactionKey());

        if ($transactionPdo instanceof PDO) {
            return $transactionPdo;
        }

        $queryPdo = $this->context->get($this->queryKey());

        if (!$queryPdo instanceof PDO) {
            $queryPdo = $this->pool->get();
            $this->context->set($this->queryKey(), $queryPdo);
        }

        return $queryPdo;
    }

    public function putPdo(PDO $pdo): void
    {
        $transactionPdo = $this->context->get($this->transactionKey());

        if ($transactionPdo instanceof PDO) {
            return;
        }

        $queryPdo = $this->context->get($this->queryKey());

        if ($queryPdo instanceof PDO && $queryPdo === $pdo) {
            $this->pool->put($pdo);
            $this->context->delete($this->queryKey());
        }
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

    private function transactionKey(): string
    {
        return "database.connection.{$this->name}.transaction_pdo";
    }

    private function queryKey(): string
    {
        return "database.connection.{$this->name}.query_pdo";
    }
}
