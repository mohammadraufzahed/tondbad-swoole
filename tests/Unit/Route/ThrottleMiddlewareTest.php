<?php

declare(strict_types=1);

use TondbadSwoole\Http\Middleware\ThrottleMiddleware;
use TondbadSwoole\Http\Request;
use TondbadSwoole\Http\Response;
use TondbadSwoole\Queue\RateLimiter\RateLimiterInterface;

class FakeRateLimiter implements RateLimiterInterface
{
    public int $attempts = 0;
    public bool $allow = true;

    public function tooManyAttempts(string $key, int $max, int $window): bool
    {
        return !$this->allow;
    }

    public function availableIn(string $key, int $window): int
    {
        return 0;
    }

    public function hit(string $key, int $window): void
    {
    }

    public function attempt(string $key, int $max, int $window): bool
    {
        $this->attempts++;

        return $this->allow;
    }
}

class FakeResponse extends Response
{
    public int $capturedStatus = 0;
    public ?string $capturedBody = null;

    public function __construct()
    {
        parent::__construct(new OpenSwoole\Http\Response());
    }

    public function status(int $status): self
    {
        $this->capturedStatus = $status;

        return $this;
    }

    public function end(?string $content = null): void
    {
        $this->capturedBody = $content;
    }
}

function makeRequest(): Request
{
    $swoole = new OpenSwoole\Http\Request();
    $swoole->server = ['request_uri' => '/api', 'remote_addr' => '127.0.0.1'];
    $swoole->header = [];
    $swoole->get = [];
    $swoole->post = [];
    $swoole->cookie = [];

    return new Request($swoole);
}

it('allows the request when the rate limiter returns true', function () {
    $request = makeRequest();
    $response = new FakeResponse();
    $limiter = new FakeRateLimiter();
    $middleware = new ThrottleMiddleware(5, 60, $limiter);

    $called = false;
    $middleware->process($request, $response, function () use (&$called): void {
        $called = true;
    });

    expect($called)->toBeTrue();
    expect($limiter->attempts)->toBe(1);
});

it('rejects the request with 429 when the rate limiter returns false', function () {
    $request = makeRequest();
    $response = new FakeResponse();
    $limiter = new FakeRateLimiter();
    $limiter->allow = false;
    $middleware = new ThrottleMiddleware(5, 60, $limiter);

    $called = false;
    $middleware->process($request, $response, function () use (&$called): void {
        $called = true;
    });

    expect($called)->toBeFalse();
    expect($response->capturedStatus)->toBe(429);
    expect($response->capturedBody)->toBe('Too Many Requests');
});
