<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Model;

class Post extends Model
{
    protected ?string $table = 'posts';

    protected array $fillable = ['user_id', 'title', 'body'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
}
