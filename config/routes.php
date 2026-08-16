<?php

declare(strict_types=1);

return [
    'http' => $env->get('routes.http', 'routes/http.php'),
    'grpc' => $env->get('routes.grpc', 'routes/grpc.php'),
    'controllers' => [],
    'file_routes' => ['enabled' => false, 'path' => 'routes/http'],
];
