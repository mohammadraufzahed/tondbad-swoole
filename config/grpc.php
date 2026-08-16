<?php

declare(strict_types=1);

use TondbadSwoole\Tests\Fixtures\Grpc\Generated\Tondbad\Test\Helloworld\GreeterGrpcAdapter;
use TondbadSwoole\Tests\Fixtures\Grpc\Services\StreamingGreeterService;

return [
    'services' => getenv('APP_ENV') === 'testing'
        ? [GreeterGrpcAdapter::class, StreamingGreeterService::class]
        : [],
    'interceptors' => [],
    'reflection' => (bool) (getenv('GRPC_REFLECTION') ?: false),
    'health' => (bool) (getenv('GRPC_HEALTH') ?: false),
    'descriptor_set' => getenv('GRPC_DESCRIPTOR_SET') ?: null,
];
