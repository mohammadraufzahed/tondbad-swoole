<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\EntityManagerInterface;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Tests\Unit\Database\Fixtures\TenantMembership;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');

    schema()->create('tenant_memberships', function (Blueprint $table) {
        $table->unsignedBigInteger('tenant_id');
        $table->unsignedBigInteger('user_id');
        $table->string('role')->nullable();
        $table->primary(['tenant_id', 'user_id']);
    });
});

afterEach(function () {
    schema()->dropIfExists('tenant_memberships');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('creates and finds an entity with a composite primary key', function () {
    $membership = TenantMembership::create([
        'tenant_id' => 1,
        'user_id' => 10,
        'role' => 'admin',
    ]);

    expect($membership->getKey())->toBe(['tenant_id' => 1, 'user_id' => 10]);

    $found = TenantMembership::find(['tenant_id' => 1, 'user_id' => 10]);

    expect($found)->toBeInstanceOf(TenantMembership::class);
    expect($found->role)->toBe('admin');
});

it('shares the same composite-key instance through EntityManager and Model', function () {
    TenantMembership::create([
        'tenant_id' => 2,
        'user_id' => 20,
        'role' => 'member',
    ]);

    $fromModel = TenantMembership::find(['tenant_id' => 2, 'user_id' => 20]);
    $fromEm = em()->find(TenantMembership::class, ['tenant_id' => 2, 'user_id' => 20]);

    expect($fromEm)->toBe($fromModel);
});

it('updates and deletes an entity with a composite primary key', function () {
    $membership = TenantMembership::create([
        'tenant_id' => 3,
        'user_id' => 30,
        'role' => 'viewer',
    ]);

    $membership->role = 'editor';
    $membership->save();

    $fresh = TenantMembership::find(['tenant_id' => 3, 'user_id' => 30]);
    expect($fresh->role)->toBe('editor');

    $fresh->delete();

    expect(TenantMembership::find(['tenant_id' => 3, 'user_id' => 30]))->toBeNull();
});

it('returns a reference for a composite primary key', function () {
    TenantMembership::create([
        'tenant_id' => 4,
        'user_id' => 40,
        'role' => 'admin',
    ]);

    $em = em();
    $em->clear();
    $reference = $em->getReference(TenantMembership::class, ['tenant_id' => 4, 'user_id' => 40]);

    expect($reference->getId())->toBe(['tenant_id' => 4, 'user_id' => 40]);
    expect($reference->role)->toBe('admin');
});
