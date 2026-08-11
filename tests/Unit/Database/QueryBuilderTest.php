<?php

declare(strict_types=1);

use TondbadSwoole\Database\Query\Grammars\MySqlGrammar;
use TondbadSwoole\Database\Query\Grammars\SqliteGrammar;
use TondbadSwoole\Tests\Unit\Database\FakeConnection;

beforeEach(function () {
    $this->mysql = new FakeConnection(new MySqlGrammar());
    $this->sqlite = new FakeConnection(new SqliteGrammar());
});

it('builds a basic select', function () {
    $sql = $this->mysql->table('users')->toSql();

    expect($sql)->toBe('select * from `users`');
});

it('quotes identifiers for sqlite', function () {
    $sql = $this->sqlite->table('users')->toSql();

    expect($sql)->toBe('select * from "users"');
});

it('selects specific columns', function () {
    $sql = $this->mysql->table('users')->select('id', 'name')->toSql();

    expect($sql)->toBe('select `id`, `name` from `users`');
});

it('adds where clauses', function () {
    $builder = $this->mysql->table('users')->where('active', true)->where('age', '>', 18);

    expect($builder->toSql())->toBe('select * from `users` where `active` = ? and `age` > ?');
    expect($builder->getBindings())->toBe([true, 18]);
});

it('adds or where clauses', function () {
    $builder = $this->mysql->table('users')->where('role', 'admin')->orWhere('role', 'editor');

    expect($builder->toSql())->toBe('select * from `users` where `role` = ? or `role` = ?');
    expect($builder->getBindings())->toBe(['admin', 'editor']);
});

it('adds where in clauses', function () {
    $builder = $this->mysql->table('users')->whereIn('role', ['admin', 'editor', 'viewer']);

    expect($builder->toSql())->toBe('select * from `users` where `role` in (?, ?, ?)');
    expect($builder->getBindings())->toBe(['admin', 'editor', 'viewer']);
});

it('adds where not in and where null', function () {
    $builder = $this->mysql->table('users')->whereNotIn('role', ['guest'])->orWhereNull('deleted_at');

    expect($builder->toSql())->toBe('select * from `users` where `role` not in (?) or `deleted_at` is null');
    expect($builder->getBindings())->toBe(['guest']);
});

it('adds where between and where not null', function () {
    $builder = $this->mysql->table('users')->whereBetween('age', [18, 65])->whereNotNull('email');

    expect($builder->toSql())->toBe('select * from `users` where `age` between ? and ? and `email` is not null');
    expect($builder->getBindings())->toBe([18, 65]);
});

it('supports nested where closures', function () {
    $builder = $this->mysql->table('users')->where('active', true)->where(function ($q) {
        $q->where('role', 'admin')->orWhere('role', 'editor');
    });

    expect($builder->toSql())->toBe('select * from `users` where `active` = ? and (`role` = ? or `role` = ?)');
    expect($builder->getBindings())->toBe([true, 'admin', 'editor']);
});

it('supports where raw with bindings', function () {
    $builder = $this->mysql->table('users')->whereRaw('`score` > ? and `level` = ?', [10, 5]);

    expect($builder->toSql())->toBe('select * from `users` where `score` > ? and `level` = ?');
    expect($builder->getBindings())->toBe([10, 5]);
});

it('supports joins', function () {
    $sql = $this->mysql->table('users')
        ->join('roles', 'users.role_id', '=', 'roles.id')
        ->toSql();

    expect($sql)->toBe('select * from `users` inner join `roles` on `users`.`role_id` = `roles`.`id`');
});

it('supports group by and order by', function () {
    $builder = $this->mysql->table('users')->groupBy('role')->having('role', '!=', 'guest')->orderByDesc('created_at')->limit(10);

    expect($builder->toSql())->toBe('select * from `users` group by `role` having `role` != ? order by `created_at` desc limit 10');
    expect($builder->getBindings())->toBe(['guest']);
});

it('supports pagination helpers', function () {
    $builder = $this->mysql->table('users')->forPage(2, 15);

    expect($builder->toSql())->toBe('select * from `users` limit 15 offset 15');
});

it('supports distinct', function () {
    $sql = $this->mysql->table('users')->distinct()->select('role')->toSql();

    expect($sql)->toBe('select distinct `role` from `users`');
});

it('builds insert sql with bindings', function () {
    $this->mysql->table('users')->insert(['name' => 'Alice', 'email' => 'alice@example.com']);

    expect($this->mysql->lastSql)->toBe('insert into `users` (`name`, `email`) values (?, ?)');
    expect($this->mysql->lastBindings)->toBe(['Alice', 'alice@example.com']);
});

it('builds multi-row insert sql', function () {
    $this->mysql->table('users')->insert([
        ['name' => 'Alice', 'email' => 'alice@example.com'],
        ['name' => 'Bob', 'email' => 'bob@example.com'],
    ]);

    expect($this->mysql->lastSql)->toBe('insert into `users` (`name`, `email`) values (?, ?), (?, ?)');
    expect($this->mysql->lastBindings)->toBe(['Alice', 'alice@example.com', 'Bob', 'bob@example.com']);
});

it('builds update sql with bindings', function () {
    $this->mysql->table('users')->where('id', 1)->update(['name' => 'Alice', 'email' => 'alice@example.com']);

    expect($this->mysql->lastSql)->toBe('update `users` set `name` = ?, `email` = ? where `id` = ?');
    expect($this->mysql->lastBindings)->toBe(['Alice', 'alice@example.com', 1]);
});

it('builds delete sql with bindings', function () {
    $this->mysql->table('users')->where('id', 1)->delete();

    expect($this->mysql->lastSql)->toBe('delete from `users` where `id` = ?');
    expect($this->mysql->lastBindings)->toBe([1]);
});

it('supports raw expressions in select', function () {
    $builder = $this->mysql->table('users')->select('id', new \TondbadSwoole\Database\Query\Expression('count(*) as total'));

    expect($builder->toSql())->toBe('select `id`, count(*) as total from `users`');
});
