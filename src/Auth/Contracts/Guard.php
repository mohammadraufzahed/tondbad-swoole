<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Contracts;

interface Guard
{
    public function check(): bool;

    public function guest(): bool;

    public function user(): ?Authenticatable;

    public function id(): string|int|null;

    public function setUser(Authenticatable $user): self;

    /**
     * @param array<string, mixed> $credentials
     */
    public function validate(array $credentials = []): bool;
}
