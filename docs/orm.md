# ORM

Tondbād's ORM is a Mikro-ORM-influenced layer on top of the query builder. It provides `Model`, `EntityManager`, `UnitOfWork`, `IdentityMap`, repositories, relations, embeddables, composite primary keys, lifecycle hooks, optimistic locking, and cascades.

## Model basics

```php
<?php

declare(strict_types=1);

namespace App\Models;

use TondbadSwoole\Database\Model;

class User extends Model
{
    protected ?string $table = 'users';

    protected array $fillable = ['name', 'email'];

    protected array $hidden = ['password'];

    protected array $casts = [
        'is_admin' => 'bool',
        'settings' => 'array',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }

    public function profile()
    {
        return $this->hasOne(Profile::class, 'user_id');
    }
}
```

## Creating and querying

```php
$user = User::create(['name' => 'Ava', 'email' => 'ava@example.com']);

$user = User::find(1);
$user = User::first();
$users = User::all();

$users = User::query()
    ->where('active', true)
    ->orderBy('created_at', 'desc')
    ->paginate(20);
```

## Updating and deleting

```php
$user->update(['name' => 'Ava Smith']);
$user->delete();
```

These operations route through the active `EntityManager` when one exists, so they participate in the per-request `UnitOfWork`.

## Entity attributes

Models can be configured with attributes instead of arrays:

```php
use TondbadSwoole\Database\Attributes\Column;
use TondbadSwoole\Database\Attributes\Entity;
use TondbadSwoole\Database\Attributes\GeneratedValue;
use TondbadSwoole\Database\Attributes\Id;
use TondbadSwoole\Database\Attributes\Table;

#[Entity]
#[Table('users')]
class User extends Model
{
    #[Id]
    #[GeneratedValue]
    protected int $id;

    #[Column('string', length: 191)]
    protected string $email;

    #[Column('json', nullable: true)]
    protected ?array $settings = null;

    #[Column('boolean', default: false, index: true)]
    protected bool $active = false;
}
```

## EntityManager and UnitOfWork

The `EntityManager` manages a per-request identity map and unit of work:

```php
$em = em();

$user = new User(['name' => 'Ava']);
$em->persist($user);
$em->flush();

$user = $em->find(User::class, 1, ['posts.comments']);
$user->name = 'Ava Smith';
$em->flush();

$em->remove($user);
$em->flush();
```

`flush()` computes the changeset and issues `INSERT`, `UPDATE`, or `DELETE` statements. `UPDATE` statements only include fields that have actually changed, and unchanged managed entities are skipped entirely. Within a request, the same entity loaded twice resolves to the same object thanks to the identity map.

`em()->clear()` detaches every managed entity, which prevents objects from leaking across OpenSwoole requests or queue jobs. The request dispatcher and queue worker call this automatically at the end of each request/job.

## Lazy references

```php
$ref = $em->getReference(User::class, 1);

// The proxy only queries the database when a property is accessed:
echo $ref->name;
```

Accessing the primary key does not trigger a load.

## Relations

```php
class User extends Model
{
    public function posts()
    {
        return $this->hasMany(Post::class, 'user_id');
    }
}

class Post extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
```

Usage:

```php
$user = User::with('posts')->find(1);

foreach ($user->posts as $post) {
    echo $post->title;
}

$post->user; // lazy loads when accessed
```

## Eager loading with nested populate

```php
$users = User::with(['posts.comments', 'profile'])->get();

// or through the EntityManager
$user = $em->find(User::class, 1, ['posts.comments']);
```

## Composite primary keys

```php
class TenantUser extends Model
{
    protected string|array $primaryKey = ['tenant_id', 'id'];
    protected string $keyType = 'int';

    protected array $fillable = ['tenant_id', 'id', 'name'];
}

$user = TenantUser::find(['tenant_id' => 1, 'id' => 5]);
```

## Embeddables

Value objects can be embedded as prefixed columns:

```php
use TondbadSwoole\Database\Attributes\Embedded;
use TondbadSwoole\Database\Attributes\Embeddable;
use TondbadSwoole\Database\Attributes\Column;

#[Embeddable]
class Address
{
    #[Column('string')]
    public string $street;

    #[Column('string')]
    public string $city;
}

class User extends Model
{
    #[Embedded(Address::class, prefix: 'address_')]
    protected Address $address;
}
```

## Lifecycle hooks

```php
use TondbadSwoole\Database\Attributes\OnCreate;
use TondbadSwoole\Database\Attributes\OnUpdate;
use TondbadSwoole\Database\Attributes\OnDelete;
use TondbadSwoole\Database\Attributes\OnLoad;

class User extends Model
{
    #[OnCreate]
    public function onCreate(): void
    {
        // runs after insert
    }

    #[OnUpdate]
    public function onUpdate(): void
    {
        // runs after update
    }

    #[OnLoad]
    public function onLoad(): void
    {
        // runs after hydration
    }
}
```

You can also subscribe externally:

```php
$em->getEventManager()->addEventListener('postLoad', function ($entity) {
    // ...
});
```

## Repositories

```php
$repo = em()->getRepository(User::class);

$user = $repo->find(1);
$users = $repo->findBy(['active' => true], ['created_at' => 'desc'], 10);
$one = $repo->findOneBy(['email' => 'ava@example.com']);
$all = $repo->findAll();

$builder = $repo->createQueryBuilder();
```

## Optimistic locking

Mark a version column with `#[Version]`:

```php
use TondbadSwoole\Database\Attributes\Version;

class Invoice extends Model
{
    #[Version]
    protected int $version = 0;
}
```

On every `UPDATE` the ORM increments `version` and adds `WHERE version = ?`. If another request changed the row, `OptimisticLockException` is thrown.

## Cascade operations

Use `#[Cascade]` on relation methods:

```php
use TondbadSwoole\Database\Attributes\Cascade;

class Team extends Model
{
    #[Cascade(['remove'])]
    public function members()
    {
        return $this->hasMany(Member::class, 'team_id');
    }
}

$team = Team::with('members')->find(1);
$team->delete(); // removes all members as well
```

## SchemaTool

Generate tables directly from entity attributes:

```php
use TondbadSwoole\Database\SchemaTool;

$tool = new SchemaTool(schema());
$tool->createSchema([User::class, Post::class]);

// Get the SQL without executing
$sql = $tool->getCreateSchemaSql([User::class, Post::class]);
$diff = $tool->getUpdateSchemaSql([User::class, Post::class]);

$tool->dropSchema([User::class]);
```

`SchemaTool` reads `#[Entity]`, `#[Table]`, `#[Id]`, `#[GeneratedValue]`, and `#[Column]` to build `Blueprint` definitions.

## Migrations

Create a migration:

```bash
php bin/tondbad make:migration create_posts_table
```

Run migrations:

```bash
php bin/tondbad migrate
php bin/tondbad migrate:fresh
php bin/tondbad migrate:rollback
php bin/tondbad migrate:status
```

Migration classes live in `database/migrations/` and use the schema builder DSL.

## Casts

Built-in casts: `string`, `int`, `float`, `bool`, `array`, `json`, `datetime`, `date`, `timestamp`, `object`, `collection`, and `enum` (for `BackedEnum`).

Custom cast:

```php
use TondbadSwoole\Database\Casts\CastsAttributes;
use TondbadSwoole\Database\Model;

class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return new Money((int) ($value * 100));
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value->toDollars();
    }
}
```

Register in `$casts`:

```php
protected array $casts = [
    'price' => MoneyCast::class,
];
```

## Advanced query layer

### Relation filtering and aggregate eager loading

```php
$users = User::whereHas('posts')->get();

$users = User::whereHas('posts', fn ($q) => $q->where('published', true))->get();

$users = User::has('posts', '>=', 3)->get();

$users = User::doesntHave('posts')->get();

$users = User::withCount('posts')->get();
// each $user->posts_count

$users = User::withSum('posts', 'views')
    ->withAvg('posts', 'views')
    ->withMax('posts', 'views')
    ->withMin('posts', 'views')
    ->get();
```

### Conditional eager loading

```php
$users = User::with(['posts' => fn ($q) => $q->where('published', true)])->get();

$user = User::find(1);
$user->loadMissing(['posts.comments']);
$user->loadCount('posts');
```

### JSON predicates and subqueries

```php
User::query()->whereJsonContains('settings->tags', 'news')->get();
User::query()->whereJsonLength('settings->tags', '>=', 2)->get();

User::query()->whereExists(
    fn ($q) => $q->from('posts')->whereColumn('posts.user_id', 'users.id')
)->get();

User::query()->whereAny(['name', 'email'], 'like', '%ava%')->get();
User::query()->whereAll(['name', 'email'], 'like', '%ava%')->get();
```

### Chunking, cursor and pagination

```php
foreach (User::cursor(1000) as $user) {
    // process
}

User::chunkById(100, function (array $users) {
    // process batch
});

$paginator = User::paginate(20, 1);
$paginator->toArray(); // items, total, per_page, current_page, last_page
```

### Find helpers and batch deletes

```php
$users = User::findMany([1, 2, 3]);

$user = User::firstOrNew(['email' => 'ava@example.com'], ['name' => 'Ava']);
$user = User::firstOrFail();

User::destroy(1);
User::destroy([1, 2, 3]);
```

### Scopes and soft deletes

```php
use TondbadSwoole\Database\Concerns\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;
}

Post::all();               // excludes deleted rows
Post::withTrashed()->get(); // includes deleted rows
Post::onlyTrashed()->get(); // only deleted rows

$post->delete();      // soft delete
$post->trashed();     // true
$post->restore();     // un-delete
$post->forceDelete(); // permanently remove
```

## Many-to-many relationships

### Defining a `BelongsToMany` relation

```php
use TondbadSwoole\Database\Model;

class User extends Model
{
    public function roles()
    {
        return $this->belongsToMany(
            Role::class,
            'role_user',   // pivot table
            'user_id',     // foreign pivot key
            'role_id',     // related pivot key
            'id',          // parent key
            'id'           // related key
        );
    }
}

class Role extends Model
{
    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user', 'role_id', 'user_id');
    }
}
```

### Loading and querying many-to-many relations

```php
$user->roles;                       // lazy load
User::with('roles')->get();         // eager load
User::withCount('roles')->get();    // adds roles_count
User::has('roles', '>=', 2)->get(); // users with at least two roles
User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->get();
```

### Pivot operations

```php
$user->roles()->attach([1, 2], ['expires_at' => '2026-12-31']);
$user->roles()->detach(2);
$user->roles()->sync([1, 3]);       // detach missing, attach new
$user->roles()->toggle([2, 3]);     // attach if missing, detach if present
$user->roles()->withPivot('expires_at')->get();
```

## Polymorphic relations

```php
class Comment extends Model
{
    public function commentable()
    {
        return $this->morphTo('commentable');
    }
}

class Post extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}

class Video extends Model
{
    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }
}
```

Polymorphic relations are constrained by both the `*_id` and `*_type` columns. `morphOne` is also available for one-to-one morphs.

```php
$post->comments;                    // Comment[] scoped to Post
Post::with('comments')->get();
Post::withCount('comments')->get();
```

## Query result cache

Model results can be cached through the framework cache store:

```php
$users = User::query()->remember(60)->get();

// custom key, e.g. for cache tags / invalidation
$admins = User::query()->where('is_admin', true)->remember(120, 'admins.list')->get();

User::query()->flushCache('admins.list');
```

`remember()` stores raw result rows and rehydrates models on cache hits, so `first()` and `get()` both respect it. The cache key defaults to a hash of the compiled SQL and bindings.
