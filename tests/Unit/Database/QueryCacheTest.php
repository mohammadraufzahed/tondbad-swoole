<?php

declare(strict_types=1);

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\Schema\Blueprint;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';
    $_ENV['CACHE_DEFAULT'] = 'in-memory';

    $this->app = new \TondbadSwoole\Bootstrap\App(__DIR__ . '/../../../..');

    cache()?->clear();

    schema()->create('query_cache_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
});

afterEach(function () {
    schema()->dropIfExists('query_cache_users');
});

class QueryCacheUser extends Model
{
    protected ?string $table = 'query_cache_users';
    protected array $fillable = ['name'];
    public bool $timestamps = false;
}

it('caches query results with remember', function () {
    QueryCacheUser::create(['name' => 'A']);
    QueryCacheUser::create(['name' => 'B']);

    $first = QueryCacheUser::query()->remember(60)->get();
    expect($first)->toHaveCount(2);

    QueryCacheUser::create(['name' => 'C']);

    $second = QueryCacheUser::query()->remember(60)->get();
    expect($second)->toHaveCount(2);

    $fresh = QueryCacheUser::query()->get();
    expect($fresh)->toHaveCount(3);
});

it('uses a custom cache key', function () {
    QueryCacheUser::create(['name' => 'A']);

    $result = QueryCacheUser::query()->remember(60, 'custom.query.key')->get();
    expect(cache()?->get('custom.query.key'))->toBeArray();
    expect($result)->toHaveCount(1);

    QueryCacheUser::query()->flushCache('custom.query.key');
    expect(cache()?->get('custom.query.key'))->toBeNull();
});

it('remembers first() results', function () {
    QueryCacheUser::create(['name' => 'A']);

    $first = QueryCacheUser::query()->orderBy('id')->remember(60)->first();
    expect($first->name)->toBe('A');

    QueryCacheUser::create(['name' => 'B']);

    $second = QueryCacheUser::query()->orderBy('id')->remember(60)->first();
    expect($second->name)->toBe('A');
});
