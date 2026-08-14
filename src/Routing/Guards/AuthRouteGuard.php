<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Guards;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Routing\Contracts\Guard;

class AuthRouteGuard implements Guard
{
    public function __construct(private readonly ?string $guard = null)
    {
    }

    public function can(Request $request): bool
    {
        return auth($this->guard)->check();
    }
}
