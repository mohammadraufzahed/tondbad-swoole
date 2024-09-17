<?php

use TondbadSwoole\Core\App;
use TondbadSwoole\Services\ExampleService;
use OpenSwoole\Http\Request;
use OpenSwoole\Http\Response;
use TondbadSwoole\Services\SumService;

require_once __DIR__ . '/../vendor/autoload.php';

// Create the application with the container
$app = App::create();

$app->get('/throw_error', function (Request $request, Response $response) {
    throw new Exception('Failed');
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

