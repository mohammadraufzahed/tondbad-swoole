<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Contracts;

interface UrlRoutable
{
    public function getRouteKey(): mixed;

    public function getRouteKeyName(): string;

    public function resolveRouteBinding(mixed $value, ?string $field = null): ?static;
}
