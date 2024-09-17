<?php

use TondbadSwoole\Core\App;
use TondbadSwoole\Core\Container;
use TondbadSwoole\Services\ExampleService;
use Swoole\Http\Request;
use Swoole\Http\Response;
use TondbadSwoole\Services\SumService;

require __DIR__ . '/../vendor/autoload.php';

// Create the application with the container
$app = App::create();

// Create the container and bind services
$app->container->singleton(ExampleService::class, function () {
    return new ExampleService();
});

$app->get('/sum', function (Request $request, Response $response, SumService $sumService) {
    $a = (int) $request->get['a'];
    $b = (int) $request->get['b'];
    return $response->end($sumService->sum($a, $b));
});

// Define a GET route that injects ExampleService automatically
$app->get('/hello/{name}', function (Request $request, Response $response, string $name, ExampleService $exampleService) {
    // Use ExampleService to generate the greeting
    $greeting = $exampleService->getGreeting($name);
    $response->end($greeting);
});

// Define a POST route
$app->post('/submit', function (Request $request, Response $response) {
    $response->end("Form Submitted!");
});

// Run the application
$app->run();
