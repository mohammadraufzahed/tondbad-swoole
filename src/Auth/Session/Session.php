<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Session;

final class Session
{
    public function __construct(
        public readonly string $id,
        public readonly string|int $userId,
        public readonly int $createdAt,
        public readonly int $expiresAt,
        public readonly array $claims = [],
        public readonly ?string $antiCsrf = null,
        public readonly ?string $deviceFingerprint = null,
        public readonly ?string $family = null,
        public readonly string $mode = 'stateful',
        public readonly string $status = 'active',
    ) {
    }

    public function withClaim(string $key, mixed $value): self
    {
        $claims = $this->claims;
        $claims[$key] = $value;

        return new self(
            $this->id,
            $this->userId,
            $this->createdAt,
            $this->expiresAt,
            $claims,
            $this->antiCsrf,
            $this->deviceFingerprint,
            $this->family,
            $this->mode,
            $this->status,
        );
    }

    public function revoked(): self
    {
        return new self(
            $this->id,
            $this->userId,
            $this->createdAt,
            $this->expiresAt,
            $this->claims,
            $this->antiCsrf,
            $this->deviceFingerprint,
            $this->family,
            $this->mode,
            'revoked',
        );
    }
}
