<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Contracts;

interface Authenticatable
{
    public function getAuthIdentifier(): string|int|null;

    public function getAuthIdentifierName(): string;

    public function getAuthPassword(): ?string;
}
