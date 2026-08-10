<?php

declare(strict_types=1);

use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Http\ResponseHelper;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new App();

$app->routes()->addRoute('GET', '/hello[/{name}]', function (Request $request, Response $response, ?string $name = '') {
    ResponseHelper::html($response, 'Hello ' . htmlspecialchars($name));
});

$app->run();
