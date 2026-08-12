<?php

declare(strict_types=1);

use TondbadSwoole\Support\Hash\HashManager;

it('hashes and checks with bcrypt', function () {
    $this->config->set('hashing.default', 'bcrypt');
    $this->config->set('hashing.drivers.bcrypt', ['rounds' => 4]);

    $hasher = new HashManager($this->config);
    $hash = $hasher->make('password');

    expect(password_verify('password', $hash))->toBeTrue();
    expect($hasher->check('password', $hash))->toBeTrue();
    expect($hasher->check('wrong', $hash))->toBeFalse();
    expect(str_starts_with($hash, '$2y$'))->toBeTrue();
});

it('detects when a bcrypt hash needs rehashing with different options', function () {
    $this->config->set('hashing.default', 'bcrypt');
    $this->config->set('hashing.drivers.bcrypt', ['rounds' => 4]);

    $hasher = new HashManager($this->config);
    $hash = $hasher->make('password');

    expect($hasher->needsRehash($hash, ['rounds' => 12]))->toBeTrue();
    expect($hasher->needsRehash($hash, ['rounds' => 4]))->toBeFalse();
});
