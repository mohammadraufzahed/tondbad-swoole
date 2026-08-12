<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Attributes\Column;
use TondbadSwoole\Database\Attributes\Entity;
use TondbadSwoole\Database\Attributes\GeneratedValue;
use TondbadSwoole\Database\Attributes\Id;
use TondbadSwoole\Database\Attributes\Table;
use TondbadSwoole\Database\Model;

#[Entity]
#[Table('products')]
class Product extends Model
{
    public bool $timestamps = false;

    #[Id]
    #[GeneratedValue]
    protected int $id;

    #[Column('string', length: 191, nullable: false)]
    protected string $name;

    #[Column('decimal', total: 10, places: 2, default: 0.0)]
    protected float $price;

    #[Column('json', nullable: true)]
    protected ?array $metadata = null;

    #[Column('boolean', default: false, index: true)]
    protected bool $active = false;
}
