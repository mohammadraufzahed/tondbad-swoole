<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\Schema\Blueprint;

class BenchmarkAdvancedUser extends Model
{
    protected ?string $table = 'bench_advanced_users';

    protected array $fillable = ['name', 'email', 'settings'];

    protected array $casts = [
        'settings' => 'array',
    ];

    public function posts()
    {
        return $this->hasMany(BenchmarkAdvancedPost::class, 'user_id');
    }
}

class BenchmarkAdvancedPost extends Model
{
    protected ?string $table = 'bench_advanced_posts';

    protected array $fillable = ['user_id', 'title', 'views'];

    public function user()
    {
        return $this->belongsTo(BenchmarkAdvancedUser::class, 'user_id');
    }
}

#[Benchmark(warmup: 2, iterations: 100, invocations: 10)]
class OrmAdvancedQueryBenchmark
{
    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        schema()->dropIfExists('bench_advanced_posts');
        schema()->dropIfExists('bench_advanced_users');

        schema()->create('bench_advanced_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        schema()->create('bench_advanced_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });

        $settings = ['tags' => ['news', 'sports', 'tech']];

        for ($i = 1; $i <= 100; ++$i) {
            $user = BenchmarkAdvancedUser::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'settings' => $settings,
            ]);

            for ($j = 0; $j < 5; ++$j) {
                BenchmarkAdvancedPost::create([
                    'user_id' => $user->id,
                    'title' => "Post {$i}-{$j}",
                    'views' => $j + 1,
                ]);
            }
        }
    }

    public function benchWhereHas(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkAdvancedUser::whereHas('posts', fn ($q) => $q->where('views', '>=', 3))->get();

        $bh->consume(count($users));
    }

    public function benchWithCount(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkAdvancedUser::withCount('posts')->withSum('posts', 'views')->get();

        $bh->consume(count($users));
    }

    public function benchPaginate(Blackhole $bh): void
    {
        em()->clear();

        $page = BenchmarkAdvancedUser::with('posts')->paginate(20, 1);

        $bh->consume($page->total());
    }

    public function benchCursor(Blackhole $bh): void
    {
        em()->clear();

        $count = 0;

        foreach (BenchmarkAdvancedUser::cursor(50) as $user) {
            ++$count;
        }

        $bh->consume($count);
    }

    public function benchJsonContains(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkAdvancedUser::query()
            ->whereJsonContains('settings->tags', 'news')
            ->get();

        $bh->consume(count($users));
    }

    public function benchFindMany(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkAdvancedUser::findMany(range(1, 25));

        $bh->consume(count($users));
    }
}
