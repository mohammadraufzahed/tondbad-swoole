# Authentication & Authorization

Tondbād Swoole uses a unified auth layer: one `Auth` facade, one `Session` value object, rotating access/refresh tokens, pluggable strategies, OIDC identity brokering, route guards, gates/policies and optional MFA. The legacy guard system (`token`, `basic`, `api_key`) remains available and is wired into the same manager.

## Configuration

`config/auth.php`:

```php
<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => 'session',
        'provider' => 'users',
    ],

    'guards' => [
        'session' => [
            'driver' => 'session',
            'provider' => 'users',
            'session_key' => 'session_id',
            'mode' => 'stateful',
            'access_ttl' => 900,
            'refresh_ttl' => 604800,
            'cookie' => [
                'http_only' => true,
                'same_site' => 'lax',
                'secure' => true,
                'path' => '/',
            ],
        ],

        'api' => [
            'driver' => 'access_token',
            'provider' => 'users',
            'session_key' => 'api_token',
            'mode' => 'stateless',
            'access_ttl' => 3600,
        ],

        'token' => [
            'driver' => 'token',
            'provider' => 'users',
            'input_key' => 'api_token',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'database',
            'table' => 'users',
            'auth_identifier' => 'id',
            'auth_password' => 'password',
        ],
    ],

    'identities' => [
        'providers' => [
            'google' => [
                'client_id' => env('GOOGLE_CLIENT_ID'),
                'client_secret' => env('GOOGLE_CLIENT_SECRET'),
                'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
                'token_endpoint' => 'https://oauth2.googleapis.com/token',
                'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
                'scope' => 'openid email profile',
            ],
        ],
    ],
];
```

`app.key` must be set and at least 32 characters long; it is used to sign compact access tokens.

## The Auth facade

`auth()` returns the `AuthManager` facade:

```php
$auth = auth();

$auth->signUp('email', ['email' => 'user@example.com', 'password' => 'secret']);
$auth->signIn('email', ['email' => 'user@example.com', 'password' => 'secret']);
$auth->signOut();

$session = $auth->session();
$user    = $auth->user();
$token   = $auth->issueApiToken($userId, ['scopes' => ['read']]);

$auth->refreshSession($refreshTokenValue);
$auth->revokeSession($sessionId);
```

`Auth` is stateless across workers: per-request state lives in `Context`, so every guard is coroutine-safe.

## Strategies

Sign-in and sign-up go through a strategy registry.

```php
auth()->signIn('email', ['email' => '...', 'password' => '...']);
auth()->signUp('email', ['email' => '...', 'password' => '...']);
```

Built-in strategies:

- `email` — email/password against the configured user provider.
- `api_key` — static or table-backed API key authentication.

Add a custom strategy:

```php
use TondbadSwoole\Auth\AuthManager;
use TondbadSwoole\Auth\Contracts\UserProvider;
use TondbadSwoole\Auth\Strategies\AuthStrategy;

app()->container->make(AuthManager::class)->addStrategy('otp', function (UserProvider $provider): AuthStrategy {
    return new OtpStrategy($provider);
});
```

## Sessions and tokens

A successful login returns an `AuthSession`:

```php
$session = auth()->signIn('email', [...]);

$session->session;      // Session value object
$session->accessToken;  // compact HMAC-SHA256 token
$session->refreshToken; // rotating refresh token (stateful mode only)
```

`Session` is immutable. Claims can be added safely during a request:

```php
auth()->addSessionClaim('mfa_verified', true);
```

Access tokens are stateless but short-lived; refresh tokens are rotated atomically and reuse of an old token revokes the whole family.

## Social / OIDC login

```php
$auth = auth();

$redirectUrl = $auth->via('google')->redirect('https://app.test/auth/callback');

// after provider callback:
$identity = $auth->via('google')->callback($code, $state, $callbackUrl);
$session  = $auth->handleIdentity($identity);
```

The broker stores `state` and a PKCE verifier in per-coroutine `Context`. The generic provider exchanges the code, fetches `userinfo` and creates an `IdentityToken`. `handleIdentity()` links the identity to an existing user or creates a new one and logs them in.

## Route guards

Protect routes using guard instances or class names:

```php
use TondbadSwoole\Routing\Guards\AuthRouteGuard;
use TondbadSwoole\Routing\Guards\RoleGuard;
use TondbadSwoole\Routing\Guards\ScopeGuard;

$route->get('/admin', [AdminController::class, 'index'])
      ->guard([AuthRouteGuard::class, RoleGuard::for('admin')]);
```

Legacy `#[Guard]` and `#[Authenticate]` attributes still work. New attributes are also available:

```php
use TondbadSwoole\Routing\Attributes\Authorize;
use TondbadSwoole\Routing\Attributes\CurrentUser;
use TondbadSwoole\Routing\Attributes\RequireMfa;

class ProfileController
{
    #[Authorize('edit-profile')]
    public function update(#[CurrentUser] Authenticatable $user): void
    {
        // ...
    }

    #[RequireMfa]
    public function transfer(): void
    {
        // ...
    }
}
```

`#[Authorize]` checks the `Gate` ability. `#[CurrentUser]` injects the authenticated user. `#[RequireMfa]` checks the `mfa_verified` session claim.

## CSRF protection

For cookie-based `session` guards, attach `VerifyCsrfToken` to state-changing routes or groups:

```php
use TondbadSwoole\Http\Middleware\VerifyCsrfToken;

$route->post('/profile', [ProfileController::class, 'update'])
      ->middleware([VerifyCsrfToken::class]);
```

The middleware compares the `X-CSRF-Token` header, `_token` form field, or `csrf_token` query parameter to the session's `antiCsrf` value.

## Multi-factor authentication

```php
$setup = mfa()->challenge($user, 'totp');
$qrUri = $setup['qr_uri'];
$secret = $setup['secret'];

if (mfa()->verify($user, 'totp', $sixDigitCode)) {
    // session claim `mfa_verified` is now true
}
```

Email OTP:

```php
$challenge = mfa()->challenge($user, 'email');
// send $challenge['code'] to the user's mailbox

if (mfa()->verify($user, 'email', $submittedCode)) {
    // mfa verified
}
```

## Gates and policies

Define abilities and policies in a service provider:

```php
use TondbadSwoole\Auth\Access\Gate;

$gate = app()->container->make(Gate::class);

$gate->define('edit-post', function (User $user, Post $post) {
    return $user->getAuthIdentifier() === $post->user_id;
});
```

Check in controllers or via the `#[Authorize]` attribute:

```php
gate()->authorize('edit-post', $post);
```

Policies are still supported; see the original authorization section of this document.

## Console commands

```bash
php bin/tondbad auth:clear-sessions
```

Revokes every row in the `sessions` table and marks all refresh tokens as revoked. Running HTTP workers keep their per-request state until the next request, when `SessionStore` will return `null`.

## Legacy guards

The `token`, `api_key` and `basic` guards are unchanged and can be used directly:

```php
if (auth('api_key')->check()) {
    $user = auth('api_key')->user();
}
```

## OpenSwoole notes

- Guards are worker singletons; no per-request state is stored on the guard object.
- `Context` is keyed by coroutine ID, so concurrent requests cannot see each other's sessions.
- Stateful sessions are stored in `SessionStore`. Use Redis (`auth.session.store => 'redis'`) for cross-worker revocation.
- The `auth:clear-sessions` command runs inside `Coroutine::run()` with `SWOOLE_HOOK_TCP` enabled so Redis/PDO I/O is safe.

For the detailed design rationale see `docs/auth-proposal.md`.
