<?php

declare(strict_types=1);

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\Relations\MorphTo;

class Post extends Model
{
    protected ?string $table = 'posts';
    protected ?string $connection = 'sqlite';
}

class Video extends Model
{
    protected ?string $table = 'videos';
    protected ?string $connection = 'sqlite';
}

class Comment extends Model
{
    protected ?string $table = 'comments';
    protected ?string $connection = 'sqlite';

    public function commentable(): MorphTo
    {
        return $this->morphTo('commentable_type', 'commentable_id', 'commentable');
    }
}
