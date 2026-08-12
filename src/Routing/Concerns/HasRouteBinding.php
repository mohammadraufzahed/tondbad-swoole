<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Concerns;

/**
 * Default implementation of the UrlRoutable contract.
 *
 * Models opt in to route model binding by implementing
 * TondbadSwoole\Routing\Contracts\UrlRoutable and using this trait.
 */
trait HasRouteBinding
{
    public function getRouteKey(): mixed
    {
        return $this->getKey();
    }

    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    public function resolveRouteBinding(mixed $value, ?string $field = null): ?static
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::firstWhere([$field => $value]);
    }
}
