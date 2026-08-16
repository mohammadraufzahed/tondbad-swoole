<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Concerns\SoftDeletes;
use TondbadSwoole\Database\Model;

class SoftUser extends Model
{
    use SoftDeletes;

    protected ?string $table = 'soft_users';

    protected array $fillable = ['name', 'email'];

    protected array $casts = [
        'deleted_at' => 'datetime',
    ];
}
