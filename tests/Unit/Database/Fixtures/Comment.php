<?php

declare(strict_types=1);

namespace TondbadSwoole\Tests\Unit\Database\Fixtures;

use TondbadSwoole\Database\Model;

class Comment extends Model
{
    protected ?string $table = 'comments';

    protected array $fillable = ['post_id', 'body'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
}
