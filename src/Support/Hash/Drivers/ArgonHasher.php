<?php

declare(strict_types=1);

namespace TondbadSwoole\Support\Hash\Drivers;

use TondbadSwoole\Support\Hash\Contracts\Hasher;

class ArgonHasher implements Hasher
{
    public function __construct(
        private readonly int $memoryCost = PASSWORD_ARGON2_DEFAULT_MEMORY_COST,
        private readonly int $timeCost = PASSWORD_ARGON2_DEFAULT_TIME_COST,
        private readonly int $threads = PASSWORD_ARGON2_DEFAULT_THREADS,
        private readonly int $algorithm = PASSWORD_ARGON2ID,
    ) {
        if (!in_array($this->algorithm, [PASSWORD_ARGON2I, PASSWORD_ARGON2ID], true)) {
            throw new \InvalidArgumentException('Unsupported Argon2 algorithm.');
        }
    }

    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, $this->algorithm, [
            'memory_cost' => $options['memory_cost'] ?? $this->memoryCost,
            'time_cost' => $options['time_cost'] ?? $this->timeCost,
            'threads' => $options['threads'] ?? $this->threads,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Argon2 hashing failed.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, $this->algorithm, [
            'memory_cost' => $options['memory_cost'] ?? $this->memoryCost,
            'time_cost' => $options['time_cost'] ?? $this->timeCost,
            'threads' => $options['threads'] ?? $this->threads,
        ]);
    }
}
