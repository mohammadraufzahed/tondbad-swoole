<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Exceptions;

use Exception;

class RevokedRefreshTokenException extends Exception
{
    private ?string $family;

    public function __construct(?string $family = null)
    {
        parent::__construct('Refresh token has been revoked or reused.');
        $this->family = $family;
    }

    public function getFamily(): ?string
    {
        return $this->family;
    }
}
