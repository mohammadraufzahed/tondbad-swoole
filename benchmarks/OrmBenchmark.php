<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Database\Attributes\Column;
use TondbadSwoole\Database\Attributes\Entity;
use TondbadSwoole\Database\Attributes\GeneratedValue;
use TondbadSwoole\Database\Attributes\Id;
use TondbadSwoole\Database\Attributes\Table;
use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\SchemaTool;

#[Entity]
#[Table('benchmark_products')]
class BenchmarkProduct extends Model
{
    public bool $timestamps = false;

    protected ?string $table = 'benchmark_products';

    protected array $fillable = ['name', 'price', 'metadata', 'active'];

    #[Id]
    #[GeneratedValue]
    protected int $id;

    #[Column('string', length: 191, nullable: false)]
    protected string $name;

    #[Column('decimal', total: 10, places: 2, default: 0.0)]
    protected float $price;

    #[Column('json', nullable: true)]
    protected ?array $metadata = null;

    #[Column('boolean', default: false, index: true)]
    protected bool $active = false;
}

#[Benchmark(warmup: 3, iterations: 500, invocations: 10)]
class OrmBenchmark
{
    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        $tool = new SchemaTool(schema());
        $tool->dropSchema([BenchmarkProduct::class]);
        $tool->createSchema([BenchmarkProduct::class]);

        $product = new BenchmarkProduct([
            'name' => 'Setup Product',
            'price' => 9.99,
            'active' => true,
        ]);

        em()->persist($product);
        em()->flush();
    }

    public function benchPersist(Blackhole $bh): void
    {
        em()->clear();

        $product = new BenchmarkProduct([
            'name' => 'Benchmark Product',
            'price' => 19.99,
            'active' => true,
        ]);

        em()->persist($product)->flush();

        $bh->consume($product->getAttribute('id'));
    }

    public function benchFind(Blackhole $bh): void
    {
        em()->clear();

        $bh->consume(em()->find(BenchmarkProduct::class, 1));
    }

    public function benchUpdate(Blackhole $bh): void
    {
        em()->clear();

        $product = em()->find(BenchmarkProduct::class, 1);

        if ($product instanceof BenchmarkProduct) {
            $product->forceFill(['name' => 'Updated ' . mt_rand()]);
            em()->persist($product)->flush();
        }

        $bh->consume($product);
    }
}
