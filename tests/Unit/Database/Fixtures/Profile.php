<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Model;

class Profile extends Model
{
    protected ?string $table = 'profiles';

    protected array $fillable = ['user_id', 'bio'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
