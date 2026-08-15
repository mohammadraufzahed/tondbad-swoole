# gRPC

Tondbād can boot an OpenSwoole-native gRPC server. It reuses the same container, providers, validation, auth, events, and cache as the HTTP server.

## Bootstrapping

Set the application type to `grpc` in `.env` or `config/app.php`:

```bash
APP_TYPE=grpc
```

```bash
php bin/tondbad serve:grpc --host=0.0.0.0 --port=9502
```

## Defining services

Services start from a `.proto` file. Place `.proto` files in `protos/` and run:

```bash
php bin/tondbad grpc:compile --proto-path=./protos --out=./generated --stub-out=./generated/Grpc
```

This command:
1. Runs `protoc --php_out` to generate message classes.
2. Builds a binary `FileDescriptorSet`.
3. Parses the descriptor set with `google/protobuf`.
4. Generates `*GrpcAdapter` (bindable service) and `*Client` stubs.

The implementation class is **not** generated. Create it yourself and the adapter delegates to it.

## Example

`protos/helloworld.proto`:

```proto
syntax = "proto3";

package helloworld;
option php_namespace = "Generated\\Helloworld";

service Greeter {
  rpc SayHello (HelloRequest) returns (HelloReply);
}

message HelloRequest {
  string name = 1;
}

message HelloReply {
  string message = 1;
}
```

Generate:

```bash
php bin/tondbad grpc:compile --proto-path=./protos --out=./generated --stub-out=./generated/Grpc --namespace-prefix=App\\Grpc\\Generated --impl-namespace=App\\Grpc\\Services --impl-suffix=Impl
```

Implement `app/Grpc/Services/GreeterImpl.php`:

```php
namespace App\Grpc\Services;

use Generated\Helloworld\HelloRequest;
use Generated\Helloworld\HelloReply;

class GreeterImpl
{
    public function sayHello(HelloRequest $request): HelloReply
    {
        $reply = new HelloReply();
        $reply->setMessage('Hello, ' . $request->getName());

        return $reply;
    }
}
```

Register the generated adapter in `config/grpc.php`:

```php
return [
    'services' => [
        \App\Grpc\Generated\Helloworld\GreeterGrpcAdapter::class,
    ],
    'interceptors' => [],
];
```

## Client

```php
use TondbadSwoole\Grpc\Channel;
use App\Grpc\Generated\Helloworld\GreeterClient;
use Generated\Helloworld\HelloRequest;

$client = new GreeterClient(new Channel('127.0.0.1', 9502));
$request = new HelloRequest();
$request->setName('World');
$reply = $client->sayHello($request);
echo $reply->getMessage(); // Hello, World
```

## Interceptors

```php
use TondbadSwoole\Grpc\Request;
use TondbadSwoole\Grpc\Response;
use TondbadSwoole\Grpc\ServerCallInfo;
use TondbadSwoole\Grpc\UnaryServerInterceptor;

class LoggingInterceptor implements UnaryServerInterceptor
{
    public function intercept(Request $request, callable $handler, ServerCallInfo $info): Response
    {
        // before
        $response = $handler($request);
        // after

        return $response;
    }
}
```

Register in `config/grpc.php`:

```php
'interceptors' => [
    \App\Grpc\LoggingInterceptor::class,
],
```

## Configuration

`config/app.php`:

```php
'grpc' => [
    'host' => $env->get('app.grpc.host', '0.0.0.0'),
    'port' => $env->get('app.grpc.port', 9502),
    'mode' => $env->get('app.grpc.mode', defined('SWOOLE_PROCESS') ? SWOOLE_PROCESS : 0),
    'sock_type' => $env->get('app.grpc.sock_type', defined('SWOOLE_SOCK_TCP') ? SWOOLE_SOCK_TCP : 0),
    'settings' => [
        'worker_num' => (int) $env->get('app.grpc.settings.worker_num', 4),
    ],
],
```

## Attribute-driven fallback

For small services, skip codegen and use attributes:

```php
use TondbadSwoole\Grpc\AttributeServiceAdapter;
use TondbadSwoole\Grpc\Attributes\AsGrpcService;
use TondbadSwoole\Grpc\Attributes\GrpcMethod;

#[AsGrpcService('helloworld.Greeter')]
class GreeterService extends AttributeServiceAdapter
{
    #[GrpcMethod('SayHello', input: HelloRequest::class, output: HelloReply::class)]
    public function sayHello(HelloRequest $request): HelloReply
    {
        // ...
    }
}
```

The server is built on `OpenSwoole\GRPC\Server`, runs each RPC in its own coroutine, and passes an immutable `Context` to every handler.
