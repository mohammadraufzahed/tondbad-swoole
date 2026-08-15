<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Comment;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Post;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Profile;
use TondbadSwoole\Database\Criteria\Criteria;
use TondbadSwoole\Database\Criteria\Restrictions;
use TondbadSwoole\Tests\Unit\Database\Fixtures\SoftUser;
use TondbadSwoole\Tests\Unit\Database\Fixtures\User;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');

    schema()->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->json('settings')->nullable();
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });

    schema()->create('posts', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreign('user_id')->references('id')->on('users');
        $table->string('title');
        $table->text('body')->nullable();
        $table->unsignedInteger('views')->default(0);
        $table->timestamps();
    });

    schema()->create('profiles', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('user_id');
        $table->foreign('user_id')->references('id')->on('users');
        $table->text('bio')->nullable();
        $table->timestamps();
    });

    schema()->create('comments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('post_id');
        $table->foreign('post_id')->references('id')->on('posts');
        $table->text('body')->nullable();
        $table->timestamps();
    });

    schema()->create('soft_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('deleted_at')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    schema()->dropIfExists('users');
    schema()->dropIfExists('posts');
    schema()->dropIfExists('profiles');
    schema()->dropIfExists('comments');
    schema()->dropIfExists('soft_users');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('filters rows with whereHas and whereRelation', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    $b = User::create(['name' => 'B', 'email' => 'b@example.com']);
    Post::create(['user_id' => $a->id, 'title' => 'A post', 'views' => 1]);
    Post::create(['user_id' => $a->id, 'title' => 'Another post', 'views' => 1]);

    $users = User::whereHas('posts')->get();
    expect($users)->toHaveCount(1);
    expect($users[0]->id)->toBe($a->id);

    $withRelation = User::whereRelation('posts', 'title', 'like', '%post%')->get();
    expect($withRelation)->toHaveCount(1);

    $without = User::doesntHave('posts')->get();
    expect($without)->toHaveCount(1);
    expect($without[0]->id)->toBe($b->id);
});

it('filters rows by relation count using has', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    $b = User::create(['name' => 'B', 'email' => 'b@example.com']);
    Post::create(['user_id' => $a->id, 'title' => 'One', 'views' => 1]);
    Post::create(['user_id' => $a->id, 'title' => 'Two', 'views' => 1]);
    Post::create(['user_id' => $b->id, 'title' => 'Three', 'views' => 1]);

    $many = User::has('posts', '>=', 2)->get();
    expect($many)->toHaveCount(1);
    expect($many[0]->id)->toBe($a->id);

    $exactlyOne = User::has('posts', '=', 1)->get();
    expect($exactlyOne)->toHaveCount(1);
    expect($exactlyOne[0]->id)->toBe($b->id);
});

it('applies eager load constraints with with callback', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    Post::create(['user_id' => $a->id, 'title' => 'Published', 'views' => 1]);
    Post::create(['user_id' => $a->id, 'title' => 'Draft', 'views' => 1]);

    $user = User::with(['posts' => fn ($q) => $q->where('title', 'Published')])->find($a->id);

    expect($user->posts)->toHaveCount(1);
    expect($user->posts[0]->title)->toBe('Published');
});

it('adds relation aggregate columns with withCount, withSum and withAvg', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    Post::create(['user_id' => $a->id, 'title' => 'One', 'views' => 10]);
    Post::create(['user_id' => $a->id, 'title' => 'Two', 'views' => 20]);

    $user = User::withCount('posts')->withSum('posts', 'views')->withAvg('posts', 'views')->find($a->id);

    expect($user->posts_count)->toBe(2);
    expect($user->posts_sum_views)->toBe(30);
    expect($user->posts_avg_views)->toBe(15.0);
});

it('loads missing relations on demand', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    $post = Post::create(['user_id' => $a->id, 'title' => 'Post', 'views' => 1]);
    Comment::create(['post_id' => $post->id, 'body' => 'Nice']);

    $user = User::find($a->id);
    expect($user->getRelation('posts'))->toBeNull();

    $user->loadMissing(['posts', 'posts.comments']);

    expect($user->posts)->toHaveCount(1);
    expect($user->posts[0]->comments)->toHaveCount(1);
});

it('loads relation counts on demand', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    Post::create(['user_id' => $a->id, 'title' => 'One', 'views' => 1]);
    Post::create(['user_id' => $a->id, 'title' => 'Two', 'views' => 1]);

    $user = User::find($a->id)->loadCount('posts');

    expect($user->posts_count)->toBe(2);
});

it('finds many by ids and finds first or new', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    $b = User::create(['name' => 'B', 'email' => 'b@example.com']);

    $found = User::findMany([$a->id, $b->id]);
    expect($found)->toHaveCount(2);

    $new = User::firstOrNew(['email' => 'c@example.com'], ['name' => 'C']);
    expect($new->exists)->toBeFalse();
    expect($new->name)->toBe('C');

    $existing = User::firstOrNew(['email' => 'a@example.com'], ['name' => 'Changed']);
    expect($existing->exists)->toBeTrue();
    expect($existing->name)->toBe('A');
});

it('throws on firstOrFail when no model exists', function () {
    User::firstOrFail();
})->throws(RuntimeException::class, 'Model not found.');

it('destroys one or many models', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    $b = User::create(['name' => 'B', 'email' => 'b@example.com']);

    expect(User::destroy($a->id))->toBe(1);
    expect(User::find($a->id))->toBeNull();

    expect(User::destroy([$b->id]))->toBe(1);
    expect(User::find($b->id))->toBeNull();
});

it('paginates results into a length aware paginator', function () {
    foreach (range(1, 10) as $i) {
        User::create(['name' => "User {$i}", 'email' => "u{$i}@example.com"]);
    }

    $page1 = User::paginate(3, 1);
    expect($page1->items())->toHaveCount(3);
    expect($page1->total())->toBe(10);
    expect($page1->perPage())->toBe(3);
    expect($page1->currentPage())->toBe(1);
    expect($page1->lastPage())->toBe(4);
    expect($page1->hasMorePages())->toBeTrue();
    expect($page1->toArray()['data'])->toHaveCount(3);

    $last = User::paginate(3, 4);
    expect($last->items())->toHaveCount(1);
    expect($last->hasMorePages())->toBeFalse();
});

it('iterates results with cursor and chunkById', function () {
    $ids = [];
    foreach (range(1, 5) as $i) {
        $ids[] = User::create(['name' => "User {$i}", 'email' => "u{$i}@example.com"])->id;
    }

    $cursorIds = [];
    foreach (User::cursor(2) as $user) {
        $cursorIds[] = $user->id;
    }
    expect($cursorIds)->toEqual($ids);

    $chunkIds = [];
    User::chunkById(2, function (array $users) use (&$chunkIds) {
        foreach ($users as $user) {
            $chunkIds[] = $user->id;
        }
    }, 'id');
    expect($chunkIds)->toEqual($ids);
});

it('filters by json contains and json length', function () {
    User::create(['name' => 'A', 'email' => 'a@example.com', 'settings' => ['tags' => ['news', 'sports']]]);
    User::create(['name' => 'B', 'email' => 'b@example.com', 'settings' => ['tags' => ['sports']]]);

    $news = User::query()->whereJsonContains('settings->tags', 'news')->get();
    expect($news)->toHaveCount(1);
    expect($news[0]->name)->toBe('A');

    $manyTags = User::query()->whereJsonLength('settings->tags', '>=', 2)->get();
    expect($manyTags)->toHaveCount(1);
    expect($manyTags[0]->name)->toBe('A');
});

it('filters with exists subquery and column comparisons', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    User::create(['name' => 'B', 'email' => 'b@example.com']);
    Post::create(['user_id' => $a->id, 'title' => 'Post', 'views' => 1]);

    $withPosts = User::query()->whereExists(fn ($q) => $q->from('posts')->whereColumn('posts.user_id', 'users.id'))->get();
    expect($withPosts)->toHaveCount(1);

    $any = User::query()->whereAny(['name', 'email'], 'like', '%a@%')->get();
    expect($any)->toHaveCount(1);
});

it('applies soft delete global scopes and restore/forceDelete', function () {
    $active = SoftUser::create(['name' => 'Active', 'email' => 'active@example.com']);
    $deleted = SoftUser::create(['name' => 'Deleted', 'email' => 'deleted@example.com']);
    $deleted->delete();

    expect($deleted->trashed())->toBeTrue();
    expect(SoftUser::all())->toHaveCount(1);
    expect(SoftUser::first()->id)->toBe($active->id);

    $withTrashed = SoftUser::withTrashed()->get();
    expect($withTrashed)->toHaveCount(2);

    $onlyTrashed = SoftUser::onlyTrashed()->get();
    expect($onlyTrashed)->toHaveCount(1);
    expect($onlyTrashed[0]->id)->toBe($deleted->id);

    $deleted->restore();
    expect($deleted->trashed())->toBeFalse();
    expect(SoftUser::all())->toHaveCount(2);

    $active->forceDelete();
    expect(SoftUser::withTrashed()->get())->toHaveCount(1);
});

it('applies Doctrine-style criteria to a model builder', function () {
    $a = User::create(['name' => 'A', 'email' => 'a@example.com']);
    $b = User::create(['name' => 'B', 'email' => 'b@example.com']);
    User::create(['name' => 'C', 'email' => 'c@example.com']);

    $criteria = Criteria::create()
        ->where('id', '>=', $a->id)
        ->where('id', '<=', $b->id)
        ->orderBy('id', 'desc')
        ->setMaxResults(2);

    $users = User::query()->applyCriteria($criteria)->get();
    expect($users)->toHaveCount(2);
    expect($users[0]->id)->toBe($b->id);
    expect($users[1]->id)->toBe($a->id);

    $restricted = User::query()->applyCriteria(
        Criteria::create()->add(Restrictions::in('id', [$a->id, $b->id]))
    )->get();
    expect($restricted)->toHaveCount(2);
});
