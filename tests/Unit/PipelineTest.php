<?php

declare(strict_types=1);

use TondbadSwoole\Core\Container;
use TondbadSwoole\Core\Pipeline\Pipeline;
use TondbadSwoole\Tests\Unit\Fixtures\AppendPipe;

it('runs pipes in order and returns the result', function () {
    $result = Pipeline::send('hello', new Container())
        ->through([
            new AppendPipe('-A'),
            new AppendPipe('-B'),
        ])
        ->thenReturn();

    expect($result)->toBe('hello-A-B');
});

it('passes the passable and next closure to callable pipes', function () {
    $result = Pipeline::send(10, new Container())
        ->through([
            fn (int $value, \Closure $next) => $next($value * 2),
            fn (int $value, \Closure $next) => $next($value + 1),
        ])
        ->thenReturn();

    expect($result)->toBe(21);
});

it('returns the destination result after pipes', function () {
    $result = Pipeline::send('base', new Container())
        ->through([
            fn (string $value, \Closure $next) => $next($value . '-piped'),
        ])
        ->then(fn (string $value) => $value . '-done');

    expect($result)->toBe('base-piped-done');
});

it('passes the value unchanged through an empty pipeline', function () {
    $result = Pipeline::send('unchanged', new Container())
        ->through([])
        ->thenReturn();

    expect($result)->toBe('unchanged');
});
