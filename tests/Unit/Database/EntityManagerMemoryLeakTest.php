<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
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

    User::create([
        'name' => 'Memory',
        'email' => 'memory@example.com',
    ]);
});

afterEach(function () {
    schema()->dropIfExists('users');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('does not leak managed entities across repeated find/flush/clear cycles', function () {
    $em = em();
    $em->clear();

    // Warm up one cycle so PHP's memory allocator stabilises before measuring.
    $user = $em->find(User::class, 1);
    $user->name = 'Warm up';
    $em->flush();
    $em->clear();

    $baseline = memory_get_usage(true);

    for ($i = 0; $i < 50; $i++) {
        $user = $em->find(User::class, 1);
        $user->name = "Name {$i}";
        $em->flush();
        $em->clear();
    }

    gc_collect_cycles();

    expect(memory_get_usage(true))->toBeLessThan($baseline + 1024 * 1024);
});
