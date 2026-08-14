<?php

declare(strict_types=1);

namespace TondbadSwoole\Routing\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    public function __construct(private readonly ?string $name = null)
    {
    }

    public function name(): ?string
    {
        return $this->name;
    }
}
