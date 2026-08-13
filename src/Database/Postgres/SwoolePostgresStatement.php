<?php

declare(strict_types=1);

namespace TondbadSwoole\Database\Postgres;

use OpenSwoole\Coroutine\PostgreSQLStatement;
use PDO;
use PDOException;
use PDOStatement;

class SwoolePostgresStatement extends PDOStatement
{
    public function __construct(
        private readonly PostgreSQLStatement $pgStmt,
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $ok = $this->pgStmt->execute($params ?? []);

        if (!$ok) {
            throw new PDOException($this->pgStmt->error ?? 'PostgreSQL statement execution failed');
        }

        return true;
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array
    {
        $rows = $this->pgStmt->fetchAll();

        if ($mode === PDO::FETCH_NUM) {
            return array_map(fn ($row) => array_values($row), $rows);
        }

        if ($mode === PDO::FETCH_BOTH) {
            $result = [];

            foreach ($rows as $row) {
                $result[] = [...array_values($row), ...$row];
            }

            return $result;
        }

        return $rows;
    }

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = $this->pgStmt->fetchAssoc();

        if ($row === false || $row === null) {
            return false;
        }

        if ($mode === PDO::FETCH_NUM) {
            return array_values($row);
        }

        return $row;
    }

    public function rowCount(): int
    {
        return $this->pgStmt->affectedRows();
    }
}
