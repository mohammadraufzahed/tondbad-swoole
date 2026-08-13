<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use TondbadSwoole\Support\Context;

$results = null;

\OpenSwoole\Coroutine::run(function () use (&$results): void {
    $context = new Context();
    $done = new \OpenSwoole\Coroutine\Channel(2);

    \OpenSwoole\Coroutine::create(function () use ($context, $done): void {
        $context->set('worker', 'a');
        \OpenSwoole\Coroutine\System::usleep(10000);
        $done->push($context->get('worker'));
    });

    \OpenSwoole\Coroutine::create(function () use ($context, $done): void {
        $context->set('worker', 'b');
        \OpenSwoole\Coroutine\System::usleep(10000);
        $done->push($context->get('worker'));
    });

    $results = [$done->pop(), $done->pop()];
});

echo json_encode($results);
