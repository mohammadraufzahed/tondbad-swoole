<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Comment;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Post;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Profile;
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
});

afterEach(function () {
    schema()->dropIfExists('users');
    schema()->dropIfExists('posts');
    schema()->dropIfExists('profiles');
    schema()->dropIfExists('comments');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('creates and retrieves a model', function () {
    $user = User::create([
        'name' => 'Alice',
        'email' => 'alice@example.com',
        'settings' => ['theme' => 'dark'],
        'is_admin' => true,
    ]);

    expect($user->id)->toBeInt();
    expect($user->name)->toBe('Alice');
    expect($user->email)->toBe('alice@example.com');
    expect($user->settings)->toBe(['theme' => 'dark']);
    expect($user->is_admin)->toBeTrue();
    expect($user->exists)->toBeTrue();

    $found = User::find($user->id);
    expect($found)->toBeInstanceOf(User::class);
    expect($found->name)->toBe('Alice');
    expect($found->is_admin)->toBeTrue();
    expect($found->settings)->toBe(['theme' => 'dark']);
});

it('updates and refreshes a model', function () {
    $user = User::create([
        'name' => 'Bob',
        'email' => 'bob@example.com',
    ]);

    $user->update(['name' => 'Bobby']);
    expect($user->name)->toBe('Bobby');

    $fresh = User::find($user->id);
    expect($fresh->name)->toBe('Bobby');
});

it('deletes a model', function () {
    $user = User::create([
        'name' => 'Carol',
        'email' => 'carol@example.com',
    ]);

    $id = $user->id;
    expect($user->delete())->toBe(1);
    expect(User::find($id))->toBeNull();
});

it('lists all models and uses query builder', function () {
    User::create(['name' => 'A', 'email' => 'a@example.com']);
    User::create(['name' => 'B', 'email' => 'b@example.com']);

    expect(User::count())->toBe(2);

    $users = User::query()->where('name', 'like', 'A%')->get();
    expect($users)->toHaveCount(1);
    expect($users[0])->toBeInstanceOf(User::class);
    expect($users[0]->name)->toBe('A');
});

it('respects fillable and guarded attributes', function () {
    $user = new User();
    $user->name = 'Mal';
    $user->email = 'mal@example.com';
    $user->is_admin = true;
    $user->save();

    expect($user->name)->toBe('Mal');
    expect($user->is_admin)->toBeTrue();
});

it('casts values to and from the database', function () {
    $user = User::create([
        'name' => 'Cast',
        'email' => 'cast@example.com',
        'settings' => ['foo' => 'bar'],
        'is_admin' => '1',
    ]);

    $fresh = User::find($user->id);
    expect($fresh->settings)->toBeArray();
    expect($fresh->settings)->toBe(['foo' => 'bar']);
    expect($fresh->is_admin)->toBeBool();
    expect($fresh->is_admin)->toBeTrue();
});

it('loads has many relations lazily and eagerly', function () {
    $user = User::create(['name' => 'Dan', 'email' => 'dan@example.com']);
    Post::create(['user_id' => $user->id, 'title' => 'First']);
    Post::create(['user_id' => $user->id, 'title' => 'Second']);

    $lazy = $user->posts;
    expect($lazy)->toBeArray();
    expect($lazy)->toHaveCount(2);
    expect($lazy[0])->toBeInstanceOf(Post::class);

    $eager = User::with('posts')->find($user->id);
    expect($eager->posts)->toHaveCount(2);
    expect($eager->posts[0]->title)->toBe('First');
});

it('loads has one relation', function () {
    $user = User::create(['name' => 'Eve', 'email' => 'eve@example.com']);
    Profile::create(['user_id' => $user->id, 'bio' => 'Hello']);

    $found = User::with('profile')->find($user->id);
    expect($found->profile)->toBeInstanceOf(Profile::class);
    expect($found->profile->bio)->toBe('Hello');
});

it('loads belongs to relation', function () {
    $user = User::create(['name' => 'Finn', 'email' => 'finn@example.com']);
    $post = Post::create(['user_id' => $user->id, 'title' => 'News']);

    expect($post->user)->toBeInstanceOf(User::class);
    expect($post->user->email)->toBe('finn@example.com');
});

it('uses firstOrCreate and updateOrCreate', function () {
    $first = User::firstOrCreate(
        ['email' => 'gwen@example.com'],
        ['name' => 'Gwen']
    );

    expect($first->name)->toBe('Gwen');

    $second = User::firstOrCreate(
        ['email' => 'gwen@example.com'],
        ['name' => 'Changed']
    );

    expect($second->name)->toBe('Gwen');

    $updated = User::updateOrCreate(
        ['email' => 'gwen@example.com'],
        ['name' => 'Gwendolyn']
    );

    expect($updated->name)->toBe('Gwendolyn');
});

it('eager loads nested relations through with', function () {
    $user = User::create([
        'name' => 'Ivy',
        'email' => 'ivy@example.com',
    ]);

    $post = Post::create(['user_id' => $user->id, 'title' => 'Hello']);
    Comment::create(['post_id' => $post->id, 'body' => 'First']);
    Comment::create(['post_id' => $post->id, 'body' => 'Second']);

    $found = User::with('posts.comments')->find($user->id);

    expect($found->posts)->toHaveCount(1);
    expect($found->posts[0]->comments)->toHaveCount(2);
    expect($found->posts[0]->comments[0]->body)->toBe('First');
});

it('loads relations on demand with load', function () {
    $user = User::create([
        'name' => 'Jack',
        'email' => 'jack@example.com',
    ]);

    Post::create(['user_id' => $user->id, 'title' => 'Hello']);

    $user = User::find($user->id);
    expect($user->getRelation('posts'))->toBeNull();

    $user->load('posts');

    expect($user->getRelation('posts'))->toHaveCount(1);
});

it('converts a model to array and json', function () {
    $user = User::create([
        'name' => 'Hank',
        'email' => 'hank@example.com',
        'settings' => ['x' => 1],
    ]);

    $array = $user->toArray();
    expect($array['name'])->toBe('Hank');
    expect($array['settings'])->toBe(['x' => 1]);

    $json = json_decode($user->toJson(), true);
    expect($json['email'])->toBe('hank@example.com');
});
