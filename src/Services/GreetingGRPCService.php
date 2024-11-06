<?php

namespace TondbadSwoole\Services;

use TondbadExample\GreetingServiceInterface;
use TondbadExample\HelloResponse;

class GreetingGRPCService implements GreetingServiceInterface
{
    public function SayHello(\OpenSwoole\GRPC\ContextInterface $ctx, \TondbadExample\HelloRequest $request): \TondbadExample\HelloResponse
    {
        $name = $request->getName();
        $out = new HelloResponse();
        $out->setMessage($name);
        return $out;
    }
}
