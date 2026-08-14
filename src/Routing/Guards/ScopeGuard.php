<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Guards;

use TondbadSwoole\Http\Request;
use TondbadSwoole\Routing\Contracts\Guard;

class ScopeGuard implements Guard
{
    /**
     * @var list<string>
     */
    private array $scopes;

    public function __construct(string|array $scopes = [])
    {
        $this->scopes = is_array($scopes) ? array_values($scopes) : [$scopes];
    }

    public static function for(string ...$scopes): self
    {
        return new self($scopes);
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

        $userScopes = $session->claims['scopes'] ?? [];

        return is_array($userScopes) && array_intersect($this->scopes, $userScopes) !== [];
    }
}
