<?php

declare(strict_types=1);

namespace TondbadSwoole\Queue\RateLimiter;

use TondbadSwoole\Database\ConnectionInterface;

class DatabaseRateLimiter implements RateLimiterInterface
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly string $table = 'rate_limits',
    ) {
    }

    public function tooManyAttempts(string $key, int $max, int $window): bool
    {
        $row = $this->connection->table($this->table)->where('key', $key)->first();

        if ($row === null) {
            return false;
        }

        $now = time();

        if ((int) $row['reset_at'] <= $now) {
            $this->reset($key, $now + $window);

            return false;
        }

        return (int) $row['count'] >= $max;
    }

    public function availableIn(string $key, int $window): int
    {
        $row = $this->connection->table($this->table)->where('key', $key)->first();

        if ($row === null) {
            return 0;
        }

        return max(0, (int) $row['reset_at'] - time());
    }

    public function hit(string $key, int $window): void
    {
        $now = time();
        $row = $this->connection->table($this->table)->where('key', $key)->first();

        if ($row === null) {
            $this->connection->table($this->table)->insert([
                'key' => $key,
                'count' => 1,
                'reset_at' => $now + $window,
            ]);

            return;
        }

        $count = (int) $row['count'] + 1;
        $resetAt = (int) $row['reset_at'];

        if ($resetAt <= $now) {
            $count = 1;
            $resetAt = $now + $window;
        }

        $this->connection->table($this->table)
            ->where('key', $key)
            ->update([
                'count' => $count,
                'reset_at' => $resetAt,
            ]);
    }

    private function reset(string $key, int $resetAt): void
    {
        $this->connection->table($this->table)
            ->where('key', $key)
            ->update([
                'count' => 0,
                'reset_at' => $resetAt,
            ]);
    }
}
