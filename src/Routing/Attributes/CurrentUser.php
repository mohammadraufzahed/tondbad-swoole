<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class CurrentUser
{
    public ?string $guard;

    public function __construct(?string $guard = null)
    {
        $this->guard = $guard;
    }
}
