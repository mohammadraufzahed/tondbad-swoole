<?php
use TondbadSwoole\Bootstrap\App;
use TondbadSwoole\Core\Route\Route;
use OpenSwoole\Http\{Request, Response};


require_once __DIR__ . '/../vendor/autoload.php';

$app = new App();

Route::addRoute('GET', '/hello[/{name}]', function (Request $request, Response $response, ?string $name = "") {
    $response->end("Hello " . $name);
});

$app->run();