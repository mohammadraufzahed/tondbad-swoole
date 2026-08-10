<?php

declare(strict_types=1);

use TondbadSwoole\Bootstrap\App;

require_once __DIR__ . '/../vendor/autoload.php';

// Force the application kernel to boot the gRPC server instead of the HTTP server.
$_ENV['APP_TYPE'] = 'grpc';

$app = new App();
$app->run();
