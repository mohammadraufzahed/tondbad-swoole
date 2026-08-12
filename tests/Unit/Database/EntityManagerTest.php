<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\EntityManagerInterface;
use TondbadSwoole\Database\Schema\Blueprint;
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
});

afterEach(function () {
    schema()->dropIfExists('users');

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
