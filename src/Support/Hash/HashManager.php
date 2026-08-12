<?php

declare(strict_types=1);

namespace TondbadSwoole\Support\Hash;

use TondbadSwoole\Core\Config;
use TondbadSwoole\Support\Hash\Contracts\Hasher;
use TondbadSwoole\Support\Hash\Drivers\ArgonHasher;
use TondbadSwoole\Support\Hash\Drivers\BcryptHasher;

class HashManager implements Hasher
{
    /**
     * @var array<string, Hasher>
     */
    private array $drivers = [];

    public function __construct(private readonly Config $config)
    {
    }

    public function driver(?string $name = null): Hasher
    {
        $name ??= $this->getDefaultDriver();

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    public function check(string $value, string $hashedValue): bool
    {
        return $this->driver()->check($value, $hashedValue);
    }

    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    public function getDefaultDriver(): string
    {
        return (string) $this->config->get('hashing.default', 'bcrypt');
    }

    private function createDriver(string $name): Hasher
    {
        $config = $this->config->get("hashing.drivers.{$name}", []);

        if (!is_array($config)) {
            throw new \InvalidArgumentException("Hash driver [{$name}] is not configured.");
        }

        return match ($name) {
            'bcrypt' => new BcryptHasher(
                (int) ($config['rounds'] ?? 10),
            ),
            'argon', 'argon2id' => new ArgonHasher(
                (int) ($config['memory_cost'] ?? PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
                (int) ($config['time_cost'] ?? PASSWORD_ARGON2_DEFAULT_TIME_COST),
                (int) ($config['threads'] ?? PASSWORD_ARGON2_DEFAULT_THREADS),
                PASSWORD_ARGON2ID,
            ),
            'argon2i' => new ArgonHasher(
                (int) ($config['memory_cost'] ?? PASSWORD_ARGON2_DEFAULT_MEMORY_COST),
                (int) ($config['time_cost'] ?? PASSWORD_ARGON2_DEFAULT_TIME_COST),
                (int) ($config['threads'] ?? PASSWORD_ARGON2_DEFAULT_THREADS),
                PASSWORD_ARGON2I,
            ),
            default => throw new \InvalidArgumentException("Hash driver [{$name}] is not supported."),
        };
    }
}
