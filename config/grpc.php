<?php

declare(strict_types=1);

use TondbadSwoole\Tests\Fixtures\Grpc\Generated\Tondbad\Test\Helloworld\GreeterGrpcAdapter;

return [
    'services' => getenv('APP_ENV') === 'testing'
        ? [GreeterGrpcAdapter::class]
        : [],
    'interceptors' => [],
];
