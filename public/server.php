<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new App();

$app->routes()->addRoute('GET', '/hello[/{name}]', function (Request $request, Response $response, ?string $name = '') {
    $response->html('Hello ' . htmlspecialchars($name ?? ''));
});

$app->run();
