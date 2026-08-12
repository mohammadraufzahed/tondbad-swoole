<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Attributes\Cascade;
use TondbadSwoole\Database\Model;

class Team extends Model
{
    protected ?string $table = 'teams';

    public bool $timestamps = false;

    protected array $fillable = ['name'];

    #[Cascade(['remove'])]
    public function members()
    {
        return $this->hasMany(Member::class, 'team_id');
    }
}
