<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\EntityRepository;
use TondbadSwoole\Database\OptimisticLockException;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Tests\Unit\Database\Fixtures\LockableInvoice;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Member;
use TondbadSwoole\Tests\Unit\Database\Fixtures\Team;
use TondbadSwoole\Tests\Unit\Database\Fixtures\User;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');

    schema()->create('lockable_invoices', function (Blueprint $table) {
        $table->id();
        $table->decimal('amount', 10, 2);
        $table->integer('version')->default(0);
    });

    schema()->create('teams', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    schema()->create('members', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('team_id');
        $table->string('name');
    });

    schema()->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->text('settings')->nullable();
        $table->boolean('is_admin')->default(false);
        $table->timestamps();
    });
});

afterEach(function () {
    schema()->dropIfExists('lockable_invoices');
    schema()->dropIfExists('teams');
    schema()->dropIfExists('members');
    schema()->dropIfExists('users');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('returns an entity repository from the entity manager', function () {
    $repository = em()->getRepository(User::class);

    expect($repository)->toBeInstanceOf(EntityRepository::class);
    expect($repository->getEntityClass())->toBe(User::class);
});

it('finds entities through the repository', function () {
    User::create(['name' => 'Repo', 'email' => 'repo@example.com']);

    $repository = em()->getRepository(User::class);

    expect($repository->find(1))->toBeInstanceOf(User::class);
    expect($repository->findBy(['name' => 'Repo']))->toHaveCount(1);
    expect($repository->findOneBy(['name' => 'Repo']))->not->toBeNull();
    expect($repository->findAll())->toHaveCount(1);
});

it('increments a version column on update', function () {
    $invoice = LockableInvoice::create(['amount' => 100, 'version' => 0]);

    expect($invoice->getVersion())->toBe(0);

    $invoice->amount = 200;
    $invoice->save();

    expect($invoice->getVersion())->toBe(1);

    $fresh = LockableInvoice::find($invoice->id);
    expect($fresh->getVersion())->toBe(1);
});

it('throws an optimistic lock exception when version changed', function () {
    $invoice = LockableInvoice::create(['amount' => 100, 'version' => 0]);

    // Simulate a concurrent update bumping the version.
    db()->table('lockable_invoices')->where('id', '=', $invoice->id)->update([
        'amount' => 999,
        'version' => 1,
    ]);

    $invoice->amount = 200;

    expect(fn () => $invoice->save())->toThrow(OptimisticLockException::class);
});

it('cascades remove to related entities', function () {
    $team = Team::create(['name' => 'A-Team']);
    Member::create(['name' => 'Hannibal', 'team_id' => $team->id]);
    Member::create(['name' => 'Face', 'team_id' => $team->id]);

    $fresh = Team::with('members')->find($team->id);

    expect($fresh->members)->toHaveCount(2);
    expect(Member::count())->toBe(2);

    $fresh->delete();

    expect(Member::count())->toBe(0);
});
