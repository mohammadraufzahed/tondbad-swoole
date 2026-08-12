<?php

declare(strict_types=1);

namespace TondbadSwoole\Support\Hash\Drivers;

use TondbadSwoole\Support\Hash\Contracts\Hasher;

class BcryptHasher implements Hasher
{
    public function __construct(private readonly int $rounds = 10)
    {
    }

    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $options['rounds'] ?? $this->rounds,
        ]);

        if ($hash === false) {
            throw new \RuntimeException('Bcrypt hashing failed.');
        }

        return $hash;
    }

    public function check(string $value, string $hashedValue): bool
    {
        return password_verify($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $options['rounds'] ?? $this->rounds,
        ]);
    }
}
