<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\EntityManagerInterface;
use TondbadSwoole\Database\Reference;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Comment;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Post;
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
        $table->string('title');
        $table->text('body')->nullable();
        $table->timestamps();
    });

    schema()->create('comments', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('post_id');
        $table->text('body')->nullable();
        $table->timestamps();
    });
});

afterEach(function () {
    schema()->dropIfExists('users');
    schema()->dropIfExists('posts');
    schema()->dropIfExists('comments');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('persists a new entity on flush', function () {
    $em = em();

    $user = new User([
        'name' => 'Alice',
        'email' => 'alice@example.com',
    ]);

    $em->persist($user)->flush();

    expect($user->exists)->toBeTrue();
    expect($user->id)->toBeInt();
    expect($em->contains($user))->toBeTrue();
});

it('returns the same instance from the identity map', function () {
    $em = em();

    $user = new User([
        'name' => 'Bob',
        'email' => 'bob@example.com',
    ]);

    $em->persist($user)->flush();

    $found = $em->find(User::class, $user->id);

    expect($found)->toBe($user);
});

it('updates managed entities on flush', function () {
    User::create([
        'name' => 'Charlie',
        'email' => 'charlie@example.com',
    ]);

    $em = em();
    $user = $em->find(User::class, 1);

    expect($user)->toBeInstanceOf(User::class);

    $user->name = 'Charles';
    $em->flush();

    $fresh = User::find(1);
    expect($fresh->name)->toBe('Charles');
});

it('removes entities on flush', function () {
    User::create([
        'name' => 'Dana',
        'email' => 'dana@example.com',
    ]);

    $em = em();
    $user = $em->find(User::class, 1);

    $em->remove($user)->flush();

    expect($user->exists)->toBeFalse();
    expect($em->find(User::class, 1))->toBeNull();
});

it('detaches a single entity on clear', function () {
    User::create([
        'name' => 'Eve',
        'email' => 'eve@example.com',
    ]);

    $em = em();
    $user = $em->find(User::class, 1);

    $em->clear($user);

    expect($em->contains($user))->toBeFalse();
    expect($em->getUnitOfWork()->isScheduledForUpdate($user))->toBeFalse();
});

it('clears the entire unit of work', function () {
    User::create([
        'name' => 'Frank',
        'email' => 'frank@example.com',
    ]);

    $em = em();
    $user = $em->find(User::class, 1);

    $em->clear();

    expect($em->contains($user))->toBeFalse();
});

it('reuses the same entity manager within a request context', function () {
    $em1 = app()->container->make(EntityManagerInterface::class);
    $em2 = app()->container->make(EntityManagerInterface::class);

    expect($em1)->toBe($em2);
});

it('shares the identity map between Model static API and EntityManager', function () {
    User::create([
        'name' => 'Merged',
        'email' => 'merged@example.com',
    ]);

    $fromModel = User::find(1);
    $fromEm = em()->find(User::class, 1);

    expect($fromEm)->toBe($fromModel);
});

it('routes Model::save through the entity manager', function () {
    $user = User::create([
        'name' => 'Nora',
        'email' => 'nora@example.com',
    ]);

    $user->name = 'Noreen';
    $user->save();

    expect(User::find(1)->name)->toBe('Noreen');
});

it('routes Model::delete through the entity manager', function () {
    User::create([
        'name' => 'Otto',
        'email' => 'otto@example.com',
    ]);

    $user = User::find(1);
    $user->delete();

    expect(em()->find(User::class, 1))->toBeNull();
});

it('returns a lazy reference from getReference', function () {
    User::create([
        'name' => 'Grace',
        'email' => 'grace@example.com',
    ]);

    $em = em();
    $em->clear();
    $reference = $em->getReference(User::class, 1);

    expect($reference)->toBeInstanceOf(Reference::class);
    expect($reference->isInitialized())->toBeFalse();
    expect($reference->getId())->toBe(1);
    expect($reference->getClassName())->toBe(User::class);
    expect((string) $reference)->toBe('1');

    expect($reference->name)->toBe('Grace');
    expect($reference->isInitialized())->toBeTrue();
});

it('returns an initialized reference when the entity is already loaded', function () {
    User::create([
        'name' => 'Hank',
        'email' => 'hank@example.com',
    ]);

    $em = em();
    $loaded = $em->find(User::class, 1);
    $reference = $em->getReference(User::class, 1);

    expect($reference->isInitialized())->toBeTrue();
    expect($reference->getValue())->toBe($loaded);
});

it('eager loads nested relations through find populate', function () {
    $user = new User([
        'name' => 'Liam',
        'email' => 'liam@example.com',
    ]);
    em()->persist($user)->flush();

    $post = Post::create(['user_id' => $user->id, 'title' => 'Hello']);
    Comment::create(['post_id' => $post->id, 'body' => 'First']);

    $found = em()->find(User::class, $user->id, ['posts.comments']);

    expect($found->posts)->toHaveCount(1);
    expect($found->posts[0]->comments)->toHaveCount(1);
    expect($found->posts[0]->comments[0]->body)->toBe('First');
});

it('loads a reference lazily when a property is accessed', function () {
    User::create([
        'name' => 'Ivy',
        'email' => 'ivy@example.com',
    ]);

    $em = em();
    $em->clear();
    $reference = $em->getReference(User::class, 1);

    expect($reference->isInitialized())->toBeFalse();
    expect($reference->id)->toBe(1);
    expect($reference->isInitialized())->toBeFalse();

    expect($reference->name)->toBe('Ivy');
    expect($reference->isInitialized())->toBeTrue();
});

it('delegates model methods through a reference', function () {
    User::create([
        'name' => 'Jack',
        'email' => 'jack@example.com',
    ]);

    $em = em();
    $em->clear();
    $reference = $em->getReference(User::class, 1);

    $array = $reference->toArray();

    expect($array['name'])->toBe('Jack');
});

it('can read the primary key from a reference without loading', function () {
    User::create([
        'name' => 'Ivy',
        'email' => 'ivy@example.com',
    ]);

    $em = em();
    $em->clear();
    $reference = $em->getReference(User::class, 1);

    expect($reference->id)->toBe(1);
    expect($reference->isInitialized())->toBeFalse();
});
