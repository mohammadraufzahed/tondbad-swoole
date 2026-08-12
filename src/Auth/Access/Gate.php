<?php

declare(strict_types=1);

namespace TondbadSwoole\Auth\Access;

use Closure;
use TondbadSwoole\Auth\Contracts\Authenticatable;

class Gate
{
    /**
     * @var array<string, callable>
     */
    private array $abilities = [];

    /**
     * @var array<class-string, class-string>
     */
    private array $policies = [];

    /**
     * @var array<string, callable>
     */
    private array $beforeCallbacks = [];

    /**
     * @param Closure(): ?Authenticatable $userResolver
     */
    public function __construct(
        private Closure $userResolver,
    ) {
    }

    public function define(string $ability, callable $callback): self
    {
        $this->abilities[$ability] = $callback;

        return $this;
    }

    /**
     * @param class-string $class
     * @param class-string $policy
     */
    public function policy(string $class, string $policy): self
    {
        $this->policies[$class] = $policy;

        return $this;
    }

    public function before(callable $callback): self
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    public function allows(string $ability, mixed $arguments = []): bool
    {
        return $this->check($ability, $arguments);
    }

    public function denies(string $ability, mixed $arguments = []): bool
    {
        return !$this->allows($ability, $arguments);
    }

    public function check(string $ability, mixed $arguments = []): bool
    {
        try {
            return $this->raw($ability, $arguments) === true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    public function authorize(string $ability, mixed $arguments = []): void
    {
        if (!$this->check($ability, $arguments)) {
            throw new AuthorizationException();
        }
    }

    public function forUser(Authenticatable $user): self
    {
        $clone = clone $this;
        $clone->userResolver = fn () => $user;

        return $clone;
    }

    private function resolveUser(): ?Authenticatable
    {
        return ($this->userResolver)();
    }

    private function raw(string $ability, mixed $arguments): mixed
    {
        $user = $this->resolveUser();
        $arguments = is_array($arguments) ? $arguments : [$arguments];

        foreach ($this->beforeCallbacks as $callback) {
            $result = $callback($user, $ability, $arguments);

            if ($result !== null) {
                return $result;
            }
        }

        if (isset($arguments[0]) && is_object($arguments[0])) {
            $policy = $this->policies[get_class($arguments[0])] ?? null;

            if ($policy !== null && method_exists($policy, $ability)) {
                return $this->callPolicy($policy, $ability, $user, $arguments);
            }
        }

        if (!isset($this->abilities[$ability])) {
            return false;
        }

        $callback = $this->abilities[$ability];

        return $callback($user, ...$arguments);
    }

    /**
     * @param class-string $policy
     * @param list<mixed> $arguments
     */
    private function callPolicy(string $policy, string $ability, ?Authenticatable $user, array $arguments): mixed
    {
        $instance = new $policy();

        if (method_exists($instance, 'before')) {
            $before = $instance->before($user, $ability, ...$arguments);

            if ($before !== null) {
                return $before;
            }
        }

        return $instance->{$ability}($user, ...$arguments);
    }
}
