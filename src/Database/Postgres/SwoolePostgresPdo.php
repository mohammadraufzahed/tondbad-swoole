<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Postgres;

use OpenSwoole\Coroutine\Context;
use OpenSwoole\Coroutine\PostgreSQL;
use PDO;
use PDOException;
use PDOStatement;

class SwoolePostgresPdo extends PDO
{
    private static int $instanceCounter = 0;

    private string $instanceId;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly array $config,
    ) {
        $this->instanceId = ++self::$instanceCounter . '_' . uniqid();
        // Intentionally do not call parent::__construct(); the OpenSwoole
        // PostgreSQL client is used instead of the native PDO pgsql driver.
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $sql = $this->normalizePlaceholders($query);
        $stmt = $this->pg()->prepare($sql);

        if ($stmt === false) {
            throw new PDOException($this->pg()->error ?? 'PostgreSQL prepare failed');
        }

        return new SwoolePostgresStatement($stmt);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $sql = $this->normalizePlaceholders($query);
        $res = $this->pg()->query($sql);

        if ($res === false) {
            throw new PDOException($this->pg()->error ?? 'PostgreSQL query failed');
        }

        return new SwoolePostgresStatement($res);
    }

    public function exec(string $statement): int|false
    {
        $res = $this->pg()->query($statement);

        if ($res === false) {
            throw new PDOException($this->pg()->error ?? 'PostgreSQL exec failed');
        }

        return $res->affectedRows();
    }

    public function lastInsertId(?string $name = null): string
    {
        if ($name !== null) {
            $sequence = $this->pg()->escape($name);
            $sql = "SELECT currval('{$sequence}') as last_id";
        } else {
            $sql = 'SELECT lastval() as last_id';
        }

        $res = $this->pg()->query($sql);

        if ($res === false) {
            throw new PDOException($this->pg()->error ?? 'PostgreSQL lastInsertId failed');
        }

        $rows = $res->fetchAll();
        $row = $rows[0] ?? null;

        if ($row === null) {
            return '0';
        }

        return (string) ($row['last_id'] ?? $row[0] ?? '0');
    }

    public function beginTransaction(): bool
    {
        $this->exec('BEGIN');
        $this->setTransactionFlag(true);

        return true;
    }

    public function commit(): bool
    {
        $this->exec('COMMIT');
        $this->setTransactionFlag(false);

        return true;
    }

    public function rollBack(): bool
    {
        $this->exec('ROLLBACK');
        $this->setTransactionFlag(false);

        return true;
    }

    public function inTransaction(): bool
    {
        return (bool) ($this->context()["{$this->pgKey()}.tx"] ?? false);
    }

    public function quote(string|int|float|bool|null $string, int $type = PDO::PARAM_STR): string|false
    {
        if ($string === null) {
            return 'NULL';
        }

        if ($type === PDO::PARAM_INT || is_int($string) || is_float($string)) {
            return (string) $string;
        }

        if ($type === PDO::PARAM_BOOL) {
            return $string ? 't' : 'f';
        }

        return $this->pg()->escapeLiteral((string) $string);
    }

    /**
     * Release the underlying PostgreSQL client for the current coroutine.
     *
     * This must be called when the PDO adapter is returned to its pool so the
     * coroutine-bound socket is closed instead of leaking or being reused in
     * another coroutine.
     */
    public function release(): void
    {
        $context = $this->context();
        $key = $this->pgKey();

        $pg = $context[$key] ?? null;

        unset($context[$key], $context["{$key}.tx"]);

        if ($pg instanceof PostgreSQL) {
            // The native client does not expose an explicit close method; it
            // closes its socket when the object is destroyed.
            $pg = null;
        }
    }

    private function pg(): PostgreSQL
    {
        $context = $this->context();
        $key = $this->pgKey();

        if (isset($context[$key]) && $context[$key] instanceof PostgreSQL) {
            return $context[$key];
        }

        $pg = new PostgreSQL();
        $conninfo = $this->buildConninfo();
        $ok = $pg->connect($conninfo);

        if (!$ok) {
            $error = $pg->error ?? 'unknown error';

            throw new PDOException("PostgreSQL connect failed: {$error}");
        }

        $context[$key] = $pg;

        return $pg;
    }

    private function setTransactionFlag(bool $flag): void
    {
        $this->context()["{$this->pgKey()}.tx"] = $flag;
    }

    private function context(): Context
    {
        return \OpenSwoole\Coroutine::getContext();
    }

    private function pgKey(): string
    {
        return "tondbad.database.pg.client.{$this->instanceId}";
    }

    /**
     * @return string
     */
    private function buildConninfo(): string
    {
        $parts = [];
        /** @var array<string, string> $mapping */
        $mapping = [
            'host' => 'host',
            'port' => 'port',
            'database' => 'dbname',
            'username' => 'user',
            'password' => 'password',
        ];

        foreach ($mapping as $configKey => $conninfoKey) {
            $value = $this->config[$configKey] ?? null;

            if ($value !== null && $value !== '') {
                $parts[] = $conninfoKey . '=' . $value;
            }
        }

        return implode(' ', $parts);
    }

    private function normalizePlaceholders(string $sql): string
    {
        $index = 0;

        $normalized = preg_replace_callback('/\?/', function () use (&$index): string {
            return '$' . ++$index;
        }, $sql);

        return $normalized ?? $sql;
    }
}
