<?php

use TondbadSwoole\Core\GrpcApp;
use TondbadSwoole\Services\GreetingGRPCService;

require_once __DIR__ . '/../vendor/autoload.php';

$grpc_server = GrpcApp::create();

$grpc_server->registerService(GreetingGRPCService::class);

$grpc_server->start();