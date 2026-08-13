<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\Database\Schema\Blueprint;
use TondbadSwoole\Queue\RateLimiter\DatabaseRateLimiter;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new App(__DIR__ . '/../../../..');

    schema()->create('rate_limits', function (Blueprint $table) {
        $table->string('key', 255);
        $table->integer('count', false, true)->default(0);
        $table->integer('reset_at', false, true);

        $table->unique('key');
    });
});

afterEach(function () {
    schema()->dropIfExists('rate_limits');

    if ($this->app instanceof App) {
        $this->app->container->make(ContextInterface::class)->clear();
    }
});

it('allows the first attempt and blocks subsequent attempts within the window', function () {
    $limiter = new DatabaseRateLimiter(db()->connection());

    expect($limiter->attempt('test-key', 1, 60))->toBeTrue();
    expect($limiter->attempt('test-key', 1, 60))->toBeFalse();
    expect($limiter->availableIn('test-key', 60))->toBeGreaterThan(0);
});

it('resets the counter when the window expires', function () {
    $limiter = new DatabaseRateLimiter(db()->connection());

    expect($limiter->attempt('test-key', 1, 1))->toBeTrue();
    expect($limiter->attempt('test-key', 1, 1))->toBeFalse();

    sleep(2);

    expect($limiter->attempt('test-key', 1, 1))->toBeTrue();
});
