<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/BenchmarkApp.php';

use TondbadSwoole\Benchmark\Attributes\Benchmark;
use TondbadSwoole\Benchmark\Attributes\Setup;
use TondbadSwoole\Benchmark\Blackhole;
use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\Schema\Blueprint;

class BenchmarkRole extends Model
{
    protected ?string $table = 'bench_roles';

    protected array $fillable = ['name'];

    public bool $timestamps = false;

    public function users()
    {
        return $this->belongsToMany(BenchmarkRoleUser::class, 'bench_role_user', 'user_id', 'role_id');
    }
}

class BenchmarkRoleUser extends Model
{
    protected ?string $table = 'bench_role_users';

    protected array $fillable = ['name'];

    public bool $timestamps = false;

    public function roles()
    {
        return $this->belongsToMany(BenchmarkRole::class, 'bench_role_user', 'user_id', 'role_id');
    }
}

class BenchmarkMorphComment extends Model
{
    protected ?string $table = 'bench_morph_comments';

    protected array $fillable = ['body', 'commentable_id', 'commentable_type'];

    public bool $timestamps = false;
}

class BenchmarkMorphPost extends Model
{
    protected ?string $table = 'bench_morph_posts';

    protected array $fillable = ['title'];

    public bool $timestamps = false;

    public function comments()
    {
        return $this->morphMany(BenchmarkMorphComment::class, 'commentable');
    }
}

#[Benchmark(warmup: 2, iterations: 100, invocations: 10)]
class OrmRelationBenchmark
{
    #[Setup]
    public function setUp(): void
    {
        BenchmarkApp::boot();

        schema()->dropIfExists('bench_role_user');
        schema()->dropIfExists('bench_role_users');
        schema()->dropIfExists('bench_roles');
        schema()->dropIfExists('bench_morph_comments');
        schema()->dropIfExists('bench_morph_posts');

        schema()->create('bench_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        schema()->create('bench_role_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });

        schema()->create('bench_role_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
        });

        schema()->create('bench_morph_posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
        });

        schema()->create('bench_morph_comments', function (Blueprint $table) {
            $table->id();
            $table->string('body');
            $table->unsignedBigInteger('commentable_id');
            $table->string('commentable_type');
        });

        for ($i = 1; $i <= 50; ++$i) {
            $user = BenchmarkRoleUser::create(['name' => "User {$i}"]);

            for ($j = 0; $j < 3; ++$j) {
                $role = BenchmarkRole::create(['name' => "role-{$i}-{$j}"]);
                $user->roles()->attach($role->id);
            }
        }

        for ($i = 1; $i <= 50; ++$i) {
            $post = BenchmarkMorphPost::create(['title' => "Post {$i}"]);

            for ($j = 0; $j < 3; ++$j) {
                BenchmarkMorphComment::create([
                    'body' => "comment-{$i}-{$j}",
                    'commentable_id' => $post->id,
                    'commentable_type' => BenchmarkMorphPost::class,
                ]);
            }
        }
    }

    public function benchBelongsToManyEagerLoad(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkRoleUser::with('roles')->get();

        $bh->consume(count($users));
    }

    public function benchBelongsToManyWithCount(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkRoleUser::withCount('roles')->get();

        $bh->consume(count($users));
    }

    public function benchBelongsToManyHasCount(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkRoleUser::has('roles', '>=', 2)->get();

        $bh->consume(count($users));
    }

    public function benchMorphManyEagerLoad(Blackhole $bh): void
    {
        em()->clear();

        $posts = BenchmarkMorphPost::with('comments')->get();

        $bh->consume(count($posts));
    }

    public function benchMorphManyWithCount(Blackhole $bh): void
    {
        em()->clear();

        $posts = BenchmarkMorphPost::withCount('comments')->get();

        $bh->consume(count($posts));
    }

    public function benchQueryCache(Blackhole $bh): void
    {
        em()->clear();

        $users = BenchmarkRoleUser::query()->remember(60)->get();

        $bh->consume(count($users));
    }
}
