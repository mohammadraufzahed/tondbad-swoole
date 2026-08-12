<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Attributes\Embeddable;

#[Embeddable]
class Address
{
    public ?string $street = null;

    public ?string $city = null;

    public function __construct(?string $street = null, ?string $city = null)
    {
        $this->street = $street;
        $this->city = $city;
    }
}
