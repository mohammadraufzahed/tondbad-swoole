<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Attributes\Embedded;
use TondbadSwoole\Database\Model;

class Company extends Model
{
    protected ?string $table = 'companies';

    public bool $timestamps = false;

    protected array $fillable = ['name', 'address'];

    #[Embedded(Address::class, prefix: 'address_')]
    protected Address $address;
}
