<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\Blackhole;

it('records that a value was consumed', function () {
    $bh = new Blackhole();

    expect($bh->consumed)->toBeFalse();

    $bh->consume('anything');

    expect($bh->consumed)->toBeTrue();
});

it('can be reset between iterations', function () {
    $bh = new Blackhole();
    $bh->consume('x');
    $bh->reset();

    expect($bh->consumed)->toBeFalse();
});
