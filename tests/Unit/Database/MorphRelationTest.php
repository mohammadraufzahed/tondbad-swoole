<?php

declare(strict_types=1);

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\Schema\Blueprint;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new \TondbadSwoole\Bootstrap\App(__DIR__ . '/../../../..');

    schema()->create('morph_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    schema()->create('morph_comments', function (Blueprint $table) {
        $table->id();
        $table->string('body');
        $table->unsignedBigInteger('commentable_id');
        $table->string('commentable_type');
    });
});

afterEach(function () {
    schema()->dropIfExists('morph_comments');
    schema()->dropIfExists('morph_videos');
    schema()->dropIfExists('morph_posts');
});

class MorphComment extends Model
{
    protected ?string $table = 'morph_comments';
    protected array $fillable = ['body', 'commentable_id', 'commentable_type'];
    public bool $timestamps = false;

    public function commentable()
    {
        return $this->morphTo('commentable');
    }
}

class MorphPost extends Model
{
    protected ?string $table = 'morph_posts';
    protected array $fillable = ['title'];
    public bool $timestamps = false;

    public function comments()
    {
        return $this->morphMany(MorphComment::class, 'commentable');
    }
}

it('loads morphMany relation', function () {
    $post = MorphPost::create(['title' => 'Post']);
    MorphComment::create(['body' => 'c1', 'commentable_id' => $post->id, 'commentable_type' => MorphPost::class]);
    MorphComment::create(['body' => 'c2', 'commentable_id' => $post->id, 'commentable_type' => MorphPost::class]);

    expect($post->comments)->toHaveCount(2);
});

it('eager loads and filters morphMany', function () {
    $post = MorphPost::create(['title' => 'Post']);
    MorphComment::create(['body' => 'c1', 'commentable_id' => $post->id, 'commentable_type' => MorphPost::class]);
    MorphComment::create(['body' => 'c2', 'commentable_id' => $post->id, 'commentable_type' => MorphPost::class]);

    $posts = MorphPost::with('comments')->get();
    expect($posts[0]->comments)->toHaveCount(2);

    $withCount = MorphPost::withCount('comments')->get();
    expect($withCount[0]->comments_count)->toBe(2);

    expect(MorphPost::has('comments', '>=', 2)->get())->toHaveCount(1);
});

it('only loads comments of the correct morph type', function () {
    class MorphVideo extends Model
    {
        protected ?string $table = 'morph_videos';
        protected array $fillable = ['title'];
        public bool $timestamps = false;

        public function comments()
        {
            return $this->morphMany(MorphComment::class, 'commentable');
        }
    }

    schema()->create('morph_videos', function (Blueprint $table) {
        $table->id();
        $table->string('title');
    });

    $post = MorphPost::create(['title' => 'Post']);
    $video = MorphVideo::create(['title' => 'Video']);

    MorphComment::create(['body' => 'pc', 'commentable_id' => $post->id, 'commentable_type' => MorphPost::class]);
    MorphComment::create(['body' => 'vc', 'commentable_id' => $video->id, 'commentable_type' => MorphVideo::class]);

    expect($post->comments)->toHaveCount(1);
    expect($video->comments)->toHaveCount(1);
    expect(MorphPost::withCount('comments')->first()->comments_count)->toBe(1);

    schema()->dropIfExists('morph_videos');
});

it('loads the inverse morphTo relation', function () {
    $post = MorphPost::create(['title' => 'Post']);
    $comment = MorphComment::create(['body' => 'c1', 'commentable_id' => $post->id, 'commentable_type' => MorphPost::class]);

    expect($comment->commentable)->toBeInstanceOf(MorphPost::class);
    expect($comment->commentable->id)->toBe($post->id);
});

it('eager loads morphTo relations', function () {
    $post = MorphPost::create(['title' => 'Post']);
    $comment = MorphComment::create(['body' => 'c1', 'commentable_id' => $post->id, 'commentable_type' => MorphPost::class]);

    $comments = MorphComment::with('commentable')->get();

    expect($comments[0]->commentable)->toBeInstanceOf(MorphPost::class);
    expect($comments[0]->commentable->title)->toBe('Post');
});
