<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Model;

class Member extends Model
{
    protected ?string $table = 'members';

    public bool $timestamps = false;

    protected array $fillable = ['name', 'team_id'];
}
