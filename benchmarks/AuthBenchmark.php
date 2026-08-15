<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Contracts\ContextInterface;
use TondbadSwoole\GRPC\GrpcHttpRequest;
use TondbadSwoole\Http\Request;

#[Benchmark(warmup: 3, iterations: 1000, invocations: 50)]
class AuthBenchmark
{
    private string $token = '';

    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();
        BenchmarkApp::migrate();

        db()->getPdo()->exec('CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY, email TEXT, password TEXT)');
        db()->getPdo()->exec("INSERT OR IGNORE INTO users (id, email, password) VALUES (1, 'test@example.com', '" . password_hash('secret', PASSWORD_BCRYPT) . "')");

        $this->token = auth()->issueApiToken(1)->value;

        $swooleRequest = new GrpcHttpRequest('', [], ['Authorization' => 'Bearer ' . $this->token]);
        auth()->setRequest(new Request($swooleRequest));
    }

    public function benchIssueApiToken(Blackhole $bh): void
    {
        $bh->consume(auth()->issueApiToken(1)->value);
    }

    public function benchCheck(Blackhole $bh): void
    {
        $context = app()->container->make(ContextInterface::class);
        $context->delete('auth.guard.access_token.user');

        $bh->consume(auth()->check());
    }
}
