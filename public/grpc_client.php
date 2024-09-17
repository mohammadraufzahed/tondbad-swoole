<?php
declare(strict_types=1);

use OpenSwoole\Constant;
use OpenSwoole\GRPC\Client;
use TondbadExample\GreetingServiceClient;
use TondbadExample\HelloRequest;

require_once __DIR__ . '/../vendor/autoload.php';

co::set(['log_level' => Constant::LOG_ERROR]);

co::run(function () {
    $conn = (new Client('127.0.0.1', port: 8080))->connect();
    $client = new GreetingServiceClient($conn);
    $message = new HelloRequest();
    $message->setName(str_repeat('xp ', 10));
    $out = $client->sayHello($message);
    var_dump($out->serializeToJsonString());
    $conn->close();
    echo "closed\n";
});
