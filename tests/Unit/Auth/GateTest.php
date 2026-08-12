<?php

declare(strict_types=1);

use TondbadSwoole\Auth\Access\AuthorizationException;
use TondbadSwoole\Auth\Access\Gate;
use TondbadSwoole\Auth\GenericUser;

it('allows an ability when the callback returns true', function () {
    $gate = new Gate(fn () => new GenericUser(['id' => 1]));
    $gate->define('edit-posts', fn ($user, $postId) => true);

    expect($gate->allows('edit-posts', 5))->toBeTrue();
});

it('denies an ability when the callback returns false', function () {
    $gate = new Gate(fn () => new GenericUser(['id' => 1]));
    $gate->define('delete-posts', fn ($user, $postId) => false);

    expect($gate->denies('delete-posts', 5))->toBeTrue();
});

it('throws an authorization exception on authorize failure', function () {
    $gate = new Gate(fn () => null);
    $gate->define('admin', fn () => false);

    $gate->authorize('admin');
})->throws(AuthorizationException::class);

it('uses a policy method when the argument class is registered', function () {
    $post = new class() {
        public int $id = 5;
    };

    $policy = new class() {
        public function update($user, $post): bool
        {
            return true;
        }
    };

    $gate = new Gate(fn () => new GenericUser(['id' => 1]));
    $gate->policy(get_class($post), get_class($policy));

    expect($gate->allows('update', $post))->toBeTrue();
});

it('evaluates before callbacks before the ability', function () {
    $gate = new Gate(fn () => new GenericUser(['id' => 1, 'is_admin' => true]));
    $gate->define('anything', fn () => false);
    $gate->before(fn ($user) => $user->get('is_admin') ? true : null);

    expect($gate->allows('anything'))->toBeTrue();
});
