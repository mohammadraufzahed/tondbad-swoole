<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Model;

class User extends Model
{
    protected ?string $table = 'users';

    protected array $fillable = ['name', 'email', 'settings', 'is_admin'];

    protected array $casts = [
        'settings' => 'array',
        'is_admin' => 'bool',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }
}
