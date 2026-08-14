<?php

declare(strict_types=1);

use OpenSwoole\GRPC\Context;
use OpenSwoole\GRPC\Constant as GrpcConstant;
use OpenSwoole\GRPC\Request as GrpcRequest;
use OpenSwoole\GRPC\RequestHandlerInterface;
use OpenSwoole\GRPC\Response as GrpcResponse;
use OpenSwoole\GRPC\Status;
use OpenSwoole\Http\Request as SwooleRequest;
use OpenSwoole\Http\Response as SwooleResponse;
use TondbadSwoole\Bootstrap\AppFactory;
use TondbadSwoole\Core\Route\Route;
use TondbadSwoole\GRPC\RouteGrpcMiddleware;

function buildGrpcRequest(string $payload, string $contentType = 'application/grpc+json'): GrpcRequest
{
    $rawRequest = new SwooleRequest();
    $rawRequest->server = ['request_uri' => '/greeter.Greeter/SayHello', 'request_method' => 'POST', 'remote_addr' => '127.0.0.1', 'remote_port' => '12345'];
    $rawRequest->header = ['content-type' => $contentType];

    $rawResponse = new SwooleResponse();

    $context = new Context([
        \OpenSwoole\Http\Request::class => $rawRequest,
        \OpenSwoole\Http\Response::class => $rawResponse,
        GrpcConstant::CONTENT_TYPE => $contentType,
        GrpcConstant::GRPC_STATUS => Status::UNKNOWN,
        GrpcConstant::GRPC_MESSAGE => '',
    ]);

    return new GrpcRequest($context, '/greeter.Greeter', 'SayHello', $payload);
}

beforeEach(function () {
    $this->tmpDir = $this->tempDir('tondbad_grpc_route_test');
    mkdir("{$this->tmpDir}/config", 0777, true);
    mkdir("{$this->tmpDir}/routes", 0777, true);
    mkdir("{$this->tmpDir}/database/migrations", 0777, true);
    mkdir("{$this->tmpDir}/storage/logs", 0777, true);
    mkdir("{$this->tmpDir}/storage/cache", 0777, true);

    file_put_contents("{$this->tmpDir}/config/app.php", "<?php\nreturn ['type' => 'grpc'];");
    file_put_contents("{$this->tmpDir}/routes/grpc.php", "<?php\nreturn function (TondbadSwoole\Core\Route\Route \$route) {\n    \$route->post('/greeter.Greeter/SayHello', function (TondbadSwoole\Http\Request \$req, TondbadSwoole\Http\Response \$res) { \$res->json(['message' => 'hello ' . \$req->input('name')]); });\n};");

    $this->app = AppFactory::create($this->tmpDir);
});

it('dispatches a grpc+json request through the http route pipeline', function () {
    $dispatcher = $this->app->container->make(Route::class)->getRouteDispatcher();
    $middleware = new RouteGrpcMiddleware($dispatcher);

    $handler = new class implements RequestHandlerInterface {
        public function handle(GrpcRequest $request): GrpcResponse
        {
            return new GrpcResponse($request->getContext(), 'not handled');
        }
    };

    $request = buildGrpcRequest(json_encode(['name' => 'World']));
    $response = $middleware->process($request, $handler);

    expect($response->getPayload())->toBe(json_encode(['message' => 'hello World']));
    expect($response->getContext()->getValue(GrpcConstant::GRPC_STATUS))->toBe(Status::OK);
});

it('maps a 404 to grpc not found when no route matches', function () {
    $dispatcher = $this->app->container->make(Route::class)->getRouteDispatcher();
    $middleware = new RouteGrpcMiddleware($dispatcher);

    $handler = new class implements RequestHandlerInterface {
        public function handle(GrpcRequest $request): GrpcResponse
        {
            return new GrpcResponse($request->getContext(), '');
        }
    };

    $request = new GrpcRequest(
        (buildGrpcRequest('{}'))->getContext()->withValue(GrpcConstant::CONTENT_TYPE, 'application/grpc+json'),
        '/unknown.Service',
        'Missing',
        '{}'
    );

    $response = $middleware->process($request, $handler);

    expect($response->getContext()->getValue(GrpcConstant::GRPC_STATUS))->toBe(Status::NOT_FOUND);
});
