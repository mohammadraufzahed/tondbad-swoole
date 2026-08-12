<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Access;

use RuntimeException;

class AuthorizationException extends RuntimeException
{
    public function __construct(string $message = 'This action is unauthorized.', int $code = 403)
    {
        parent::__construct($message, $code);
    }
}
