<?php

declare(strict_types=1);

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Validation\Schema;
use TondbadSwoole\Validation\Validator;

#[Benchmark(warmup: 3, iterations: 5000, invocations: 100)]
class ValidationBenchmark
{
    private Schema $schema;

    /** @var array<string, string> */
    private array $rules = [];

    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct()
    {
        $this->schema = Schema::object([
            'email' => Schema::string()->email()->required(),
            'password' => Schema::string()->min(8)->required(),
            'age' => Schema::int()->coerce()->gte(18)->nullable()->default(null),
            'tags' => Schema::array(Schema::string())->max(10)->default([]),
        ])->lax();

        $this->rules = [
            'email' => 'required|email',
            'password' => 'required|min:8',
            'age' => 'nullable|int|gte:18',
            'tags' => 'array',
        ];

        $this->data = [
            'email' => 'user@example.com',
            'password' => 'password123',
            'age' => '25',
            'tags' => ['php', 'swoole'],
        ];
    }

    public function benchSchema(): void
    {
        $this->schema->safeParse($this->data);
    }

    public function benchValidator(): void
    {
        $validator = new Validator($this->data, $this->rules);
        $validator->passes();
    }
}
