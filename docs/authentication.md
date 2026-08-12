# Authentication & Authorization

Tondbād provides guard-based authentication and gate/policy authorization.

## Configuration

`config/auth.php`:

```php
<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => 'token',
        'passwords' => 'users',
    ],

    'guards' => [
        'token' => [
            'driver' => 'token',
            'provider' => 'users',
            'input_key' => 'api_token',
        ],

        'session' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        'api-key' => [
            'driver' => 'api_key',
            'provider' => 'users',
        ],

        'basic' => [
            'driver' => 'basic',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
    ],
];
```

## User model

A model authenticates by using the `Authenticatable` trait and implementing the `Authenticatable` contract:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use TondbadSwoole\Auth\Concerns\Authenticatable;
use TondbadSwoole\Auth\Contracts\Authenticatable as AuthenticatableContract;
use TondbadSwoole\Database\Model;

class User extends Model implements AuthenticatableContract
{
    use Authenticatable;

    protected ?string $table = 'users';

    protected array $fillable = ['email', 'password', 'api_token'];

    protected array $hidden = ['password'];
}
```

## Guard usage

```php
$auth = auth(); // default guard's AuthManager facade

if ($auth->check()) {
    $user = $auth->user();
}
```

Specific guard:

```php
$guard = auth('api-key');

if ($guard->check()) {
    $user = $guard->user();
}
```

Validate credentials:

```php
if (auth('session')->validate(['email' => '...', 'password' => '...'])) {
    // credentials are valid
}
```

## Session guard

The `session` guard supports explicit login/logout by storing the user in the request context:

```php
$session = auth('session');

if ($session->validate(['email' => $email, 'password' => $password])) {
    $sessionId = $session->login($user);
}

$session->logout();
```

In Swoole, session state is per-request and cleared at the end of each dispatch.

## Token guard

The `token` guard reads an `api_token` field from a header or query parameter by default. The input key is configurable per guard.

## Basic auth guard

The `basic` guard validates HTTP Basic credentials against the configured user provider.

## API key guard

The `api_key` guard checks a dedicated `api_keys` table. Keys can be per-user with expiration and active state.

## Middleware and attributes

Use `Authenticate` middleware:

```php
$route->get('/dashboard', [DashboardController::class, 'index'], [\TondbadSwoole\Http\Middleware\Authenticate::class]);
```

Or the `#[Authenticate]` attribute on a controller method:

```php
use TondbadSwoole\Http\Attributes\Authenticate;

class DashboardController
{
    #[Authenticate]
    public function index(Request $request, Response $response): void
    {
        $response->json(['user' => auth()->user()?->toArray()]);
    }
}
```

Specify a guard:

```php
#[Authenticate('api-key')]
```

## Hashing

Hash and verify passwords through the `HashManager`:

```php
$hasher = app()->container->make(\TondbadSwoole\Support\Hash\HashManager::class);

$hash = $hasher->make('plain-password');

if ($hasher->check('plain-password', $hash)) {
    // valid
}

if ($hasher->needsRehash($hash)) {
    $hash = $hasher->make('plain-password');
}
```

CLI helpers:

```bash
php bin/tondbad hash:make mypassword
php bin/tondbad hash:check mypassword '$2y$...'
```

## Authorization

Define abilities and policies in a service provider:

```php
use TondbadSwoole\Auth\Access\Gate;

$gate = app()->container->make(Gate::class);

$gate->define('edit-post', function (User $user, Post $post) {
    return $user->id === $post->user_id;
});
```

Check in routes or controllers:

```php
if (gate()->allows('edit-post', $post)) {
    // ...
}

gate()->authorize('edit-post', $post); // throws AuthorizationException on denial
```

## Policies

Create a policy:

```bash
php bin/tondbad make:policy PostPolicy
```

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;
use TondbadSwoole\Auth\Access\Policy;

class PostPolicy extends Policy
{
    public function update(User $user, Post $post): bool
    {
        return $user->id === $post->user_id;
    }
}
```

Register the policy:

```php
$gate->policy(Post::class, PostPolicy::class);
```

Then `gate()->authorize('update', $post)` delegates to `PostPolicy::update()`.

## Custom guards

Register a guard factory in a service provider:

```php
use TondbadSwoole\Auth\AuthManager;

app()->container->make(AuthManager::class)->extend('jwt', function ($container, $provider, $config, $name) {
    return new \App\Auth\Guards\JwtGuard($container, $provider, $config, $name);
});
```

Or implement `GuardFactory` and pass the class name:

```php
use TondbadSwoole\Auth\AuthManager;

app()->container->make(AuthManager::class)->extend('jwt', \App\Auth\Guards\JwtGuardFactory::class);
```

Create a factory stub with:

```bash
php bin/tondbad make:guard JwtGuardFactory
```

This writes `app/Auth/Guards/JwtGuardFactory.php` with the `GuardFactory` contract, ready to wire in `config/auth.php`.
