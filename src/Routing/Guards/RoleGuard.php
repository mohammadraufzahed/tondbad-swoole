<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Guards;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Routing\Contracts\Guard;

class RoleGuard implements Guard
{
    /**
     * @var list<string>
     */
    private array $roles;

    public function __construct(string|array $roles = [])
    {
        $this->roles = is_array($roles) ? array_values($roles) : [$roles];
    }

    public static function for(string ...$roles): self
    {
        return new self($roles);
    }

    public function can(Request $request): bool
    {
        if (!auth()->check()) {
            return false;
        }

        $session = auth()->session();

        if ($session === null) {
            return false;
        }

        $userRoles = $session->claims['roles'] ?? [];

        return is_array($userRoles) && array_intersect($this->roles, $userRoles) !== [];
    }
}
