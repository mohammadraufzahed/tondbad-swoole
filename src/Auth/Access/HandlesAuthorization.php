<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Access;

trait HandlesAuthorization
{
    /**
     * Authorize the given action.
     */
    protected function allow(): true
    {
        return true;
    }

    /**
     * Deny the given action and throw an exception.
     */
    protected function deny(string $message = 'This action is unauthorized.', int $code = 403): never
    {
        throw new AuthorizationException($message, $code);
    }
}
