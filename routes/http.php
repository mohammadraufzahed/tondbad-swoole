<?php

declare(strict_types=1);

use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;

return function (Route $route): void {
    $route->addRoute('GET', '/hello[/{name}]', function (Request $request, Response $response, ?string $name = '') {
        $response->html('Hello ' . htmlspecialchars($name ?? ''));
    });
};
