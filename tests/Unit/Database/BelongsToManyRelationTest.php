<?php

declare(strict_types=1);

use TondbadSwoole\Database\Model;
use TondbadSwoole\Database\Schema\Blueprint;

beforeEach(function () {
    $_ENV['DB_CONNECTION'] = 'sqlite';
    $_ENV['APP_TYPE'] = 'http';

    $this->app = new \TondbadSwoole\Bootstrap\App(__DIR__ . '/../../../..');

    schema()->create('btm_roles', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    schema()->create('btm_users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    schema()->create('btm_role_user', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('btm_user_id');
        $table->unsignedBigInteger('btm_role_id');
    });
});

afterEach(function () {
    schema()->dropIfExists('btm_role_user');
    schema()->dropIfExists('btm_users');
    schema()->dropIfExists('btm_roles');
});

class BtmRole extends Model
{
    protected ?string $table = 'btm_roles';
    protected array $fillable = ['name'];
    public bool $timestamps = false;

    public function users()
    {
        return $this->belongsToMany(BtmUser::class, 'btm_role_user', 'btm_user_id', 'btm_role_id');
    }
}

class BtmUser extends Model
{
    protected ?string $table = 'btm_users';
    protected array $fillable = ['name'];
    public bool $timestamps = false;

    public function roles()
    {
        return $this->belongsToMany(BtmRole::class, 'btm_role_user', 'btm_user_id', 'btm_role_id');
    }
}

it('attaches and detaches related ids', function () {
    $user = BtmUser::create(['name' => 'Ava']);
    $admin = BtmRole::create(['name' => 'admin']);
    $editor = BtmRole::create(['name' => 'editor']);

    $user->roles()->attach([$admin->id, $editor->id]);
    expect($user->roles)->toHaveCount(2);

    $user->roles()->detach($editor->id);
    $user->load('roles');
    expect($user->roles)->toHaveCount(1);
});

it('syncs related ids', function () {
    $user = BtmUser::create(['name' => 'Ava']);
    $admin = BtmRole::create(['name' => 'admin']);
    $editor = BtmRole::create(['name' => 'editor']);
    $viewer = BtmRole::create(['name' => 'viewer']);

    $user->roles()->attach([$admin->id, $editor->id]);

    $result = $user->roles()->sync([$editor->id, $viewer->id]);
    expect($result['attached'])->toContain((string) $viewer->id);
    expect($result['detached'])->toContain((string) $admin->id);
    expect($user->fresh()->roles)->toHaveCount(2);
});

it('toggles related ids', function () {
    $user = BtmUser::create(['name' => 'Ava']);
    $admin = BtmRole::create(['name' => 'admin']);

    $user->roles()->attach($admin->id);
    $result = $user->roles()->toggle([$admin->id, BtmRole::create(['name' => 'editor'])->id]);

    expect($result['detached'])->toContain((string) $admin->id);
    expect($result['attached'])->toHaveCount(1);
    expect($user->fresh()->roles)->toHaveCount(1);
});

it('eager loads many-to-many relations', function () {
    $userA = BtmUser::create(['name' => 'A']);
    $userB = BtmUser::create(['name' => 'B']);
    $admin = BtmRole::create(['name' => 'admin']);
    $editor = BtmRole::create(['name' => 'editor']);

    $userA->roles()->attach([$admin->id, $editor->id]);
    $userB->roles()->attach($admin->id);

    $users = BtmUser::with('roles')->get();
    expect($users[0]->roles)->toHaveCount(2);
    expect($users[1]->roles)->toHaveCount(1);
});

it('filters by relation existence and count', function () {
    $userA = BtmUser::create(['name' => 'A']);
    $userB = BtmUser::create(['name' => 'B']);
    $admin = BtmRole::create(['name' => 'admin']);
    $editor = BtmRole::create(['name' => 'editor']);

    $userA->roles()->attach([$admin->id, $editor->id]);
    $userB->roles()->attach($admin->id);

    expect(BtmUser::has('roles')->get())->toHaveCount(2);
    expect(BtmUser::has('roles', '>=', 2)->get())->toHaveCount(1);
    expect(BtmUser::doesntHave('roles')->get())->toHaveCount(0);
    expect(BtmUser::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get())->toHaveCount(2);
});

it('counts many-to-many relations', function () {
    $userA = BtmUser::create(['name' => 'A']);
    $userB = BtmUser::create(['name' => 'B']);
    $admin = BtmRole::create(['name' => 'admin']);
    $editor = BtmRole::create(['name' => 'editor']);

    $userA->roles()->attach([$admin->id, $editor->id]);
    $userB->roles()->attach($admin->id);

    $users = BtmUser::withCount('roles')->get();
    expect($users[0]->roles_count)->toBe(2);
    expect($users[1]->roles_count)->toBe(1);
});
