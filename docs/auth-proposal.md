# Auth module next-level proposal (bullet-proof v2)

## Goal

Evolve the existing `AuthManager` / guards / `Gate` / route attributes into one production-grade authentication layer for Tondbād Swoole. We borrow ideas from:

- **Better Auth** — plugin-driven auth engine, stateless signed session cookies, session caching, and a unified `AuthContext`.
- **SuperTokens** — access/refresh token pairs, rotating refresh tokens with reuse detection, session handles, anti-CSRF, and explicit `verifySession` / `refreshSession` APIs.
- **Keycloak** — OIDC identity brokering, account linking, and first-login flows.

The output is **not** a clone of any of them. It is one `Auth` facade, one `Session` model, and one `Guard` pipeline that fits the existing `AuthManager`, routing `#[Guard]` / `#[Authenticate]`, `Gate`, and the new `HybridStore` cache.

---

## Hard design constraints

These are non-negotiable because of OpenSwoole, the current framework, and PHP:

1. **Guards are long-lived objects.** `AuthManager` is a worker singleton and caches guard instances. Guards must not store per-request state in properties. The current `SessionGuard::$sessionId` property is a bug under coroutine concurrency and must be removed.
2. **Per-request auth state lives in `Context`.** `RouteDispatcher` clears `Context` at the start and end of every request, so `auth()->user()` is isolated across coroutines.
3. **Session revocation must be global.** The application `HybridStore` L1 (`OpenSwoole\Table`) is per-worker. If sessions live in `HybridStore`, a logout on worker A is invisible to worker B until L1 TTL expires. Therefore `SessionManager` uses a dedicated, L2-first session store (Redis or DB), not the application `cache()` L1, unless the user explicitly opts into a single-worker deployment.
4. **Refresh-token rotation must be atomic.** Two concurrent refresh requests with the same refresh token must produce exactly one new pair and revoke the family on reuse. This requires a DB `UPDATE ... WHERE used_at IS NULL` or `ChannelLock` around the rotate step.
5. **Stateless sessions cannot be instantly revoked.** If we sign the session into the access token, revocation is delayed until expiry or until a version counter is checked. The access token must be short-lived (minutes, not hours) and refresh must require the backend.
6. **OIDC HTTP calls must be coroutine-native.** We use `OpenSwoole\Coroutine\Http\Client` (or `HOOK_NATIVE_CURL`) for discovery, token exchange, and userinfo. No blocking `file_get_contents`.
7. **Token signing uses `app.key`.** We use HMAC-SHA256 with `hash_hmac` and base64url. A separate `auth.refresh_secret` can be added later; initially one secret is enough and must be configured.

---

## What is wrong with the first draft

The first proposal was directionally right but left several dangerous gaps:

| Problem | Why it matters | Fix in this draft |
|---------|--------------|-------------------|
| Sessions stored in the generic `cache()` `HybridStore` | Per-worker L1 makes logout not global. | `SessionManager` uses a dedicated `SessionStore` (Redis/DB, L2-first). |
| `AccessToken` / `Session` shape undefined | `DateTimeImmutable` in a value object breaks JSON serialization in `RedisCache`. | Use `int $expiresAt`, `int $createdAt`, `array $claims`. |
| `AuthStrategy::signUp` assumed every strategy signs up | OIDC and API-key strategies do not. | Split into `authenticate()` (login/link) and `register()` (create local account). |
| No atomic refresh-token rotation | Concurrent refresh causes duplicate sessions. | `RefreshTokenRepository::rotate()` uses atomic compare-and-set (`used_at` guard + `ChannelLock`). |
| No anti-CSRF for cookie sessions | Cookie sessions without anti-CSRF are vulnerable. | `Session` carries `antiCsrf`; state-changing routes use `VerifyCsrfToken` middleware or `#[ValidateCsrf]`. |
| No clear separation of `web` vs `api` guards | Web uses cookies; API uses Bearer. | `SessionGuard` is cookie-based; `AccessTokenGuard` is Bearer-based; both verify the same `AccessToken` format. |
| `AuthRouteGuard` redundant | `Authenticate` middleware already exists. | Reuse `Authenticate` middleware / `#[Authenticate]`; add `#[Guard]` for custom, and `#[Authorize]` for policy gates. |
| No session claim update path | MFA verification must update the session. | `SessionManager::addClaim($sessionId, $claim, $value)` reissues cookie/token when needed. |
| No `StatefulGuard` contract | `login()` / `logout()` are not part of `Guard`. | Introduce `StatefulGuard extends Guard` with `login`/`logout`; `Auth` facade only calls them on stateful guards. |
| No mention of password rehashing | `DatabaseUserProvider` never rehashes. | Login flow checks `password_needs_rehash` and updates the user record. |
| No user-creation abstraction | `UserProvider` cannot create users. | `AuthUserManager` / `UserFactory` creates users for registration and OIDC linking. |

---

## Unified architecture

### One public API: `Auth`

`auth()` keeps returning `AuthManager` for backward compatibility, but `AuthManager` grows the following methods:

```php
$auth = auth();

$auth->check();                       // is a user/session attached?
$auth->user();                        // Authenticatable or null
$auth->session();                     // Session or null

$auth->register('email', [...]);      // create user and log in
$auth->login('email', [...]);         // return Session
$auth->logout();                      // revoke current session
$auth->refreshSession($refreshToken); // return new Session

$auth->issueApiToken($userId, ['scopes' => ['read'], 'expires' => 3600]);
$auth->revokeSession($sessionId);
$auth->revokeAllSessions($userId);

$auth->identity('google')->redirect($state);     // OIDC redirect URL
$auth->identity('google')->callback($code, $state); // user + new session

$auth->guard('api');                  // still works
$auth->guard('web')->login(...);     // stateful guard
```

Guards become thin, stateless adapters:

- `SessionGuard` reads `session_id` cookie → verifies access token → sets `Context`.
- `AccessTokenGuard` reads `Authorization: Bearer <token>` → verifies access token → sets `Context`.
- `ApiKeyGuard` remains for long-lived API keys (separate contract).
- `BasicAuthGuard` validates per request.

Only `SessionGuard` is a `StatefulGuard` (has `login`/`logout` and sets cookies). `AccessTokenGuard` is stateless verification only.

### One token/session model

```php
final class Session
{
    public function __construct(
        public readonly string $id,          // UUID session handle
        public readonly string|int $userId,
        public readonly int $createdAt,
        public readonly int $expiresAt,
        public readonly array $claims,       // roles, scopes, mfa_verified, custom
        public readonly ?string $antiCsrf,
        public readonly ?string $deviceFingerprint,
    ) {}
}

final class AccessToken
{
    public function __construct(
        public readonly string $value,        // base64url signed token
        public readonly string $sessionId,
        public readonly int $expiresAt,
        public readonly array $claims,
    ) {}
}

final class RefreshToken
{
    public function __construct(
        public readonly string $value,
        public readonly string $sessionId,
        public readonly string $family,
        public readonly int $expiresAt,
    ) {}
}
```

The access token is a compact signed token with this payload:

```json
{
    "sid": "<session-id>",
    "sub": "<user-id>",
    "exp": 1234567890,
    "claims": { "roles": ["user"], "mfa_verified": true },
    "jti": "<token-id>"
}
```

`AccessTokenManager` signs/verifies with HMAC-SHA256. Verification only checks signature and expiry. For **stateless** mode the payload also contains the full claim set and no DB lookup is needed. For **stateful** mode the payload contains the session id and `SessionManager` loads the session row/cache to confirm it is not revoked.

### Two session modes

| Mode | Use case | Revocation | Lookup |
|------|----------|------------|--------|
| `stateful` | Web apps, when instant logout matters | Immediate (`sessions.status = 'revoked'`) | Cache/DB on every request |
| `stateless` | High-throughput APIs | Delayed until access token expiry; optional version check | None (or one version lookup) |

Configuration example:

```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'mode'   => 'stateful',
        'access_ttl'  => 900,
        'refresh_ttl' => 604800,
        'cookie' => ['name' => 'session_id', 'http_only' => true, 'same_site' => 'lax'],
    ],
    'api' => [
        'driver' => 'token',
        'mode'   => 'stateless',
        'access_ttl' => 900,
    ],
],
```

### Dedicated `SessionStore`

`SessionManager` depends on `SessionStore`, not `Cache`:

```php
interface SessionStore
{
    public function get(string $sessionId): ?Session;
    public function set(Session $session, int $ttl): void;
    public function delete(string $sessionId): void;
    public function addClaim(string $sessionId, string $claim, mixed $value): void;
    public function revokeFamily(string $family): void;
}
```

Implementations:

- `RedisSessionStore` (default for production): uses `RedisCache` directly, L2 only, with `eval`/`del` for cross-worker consistency.
- `DatabaseSessionStore` (fallback): uses `sessions` table.
- `CachedSessionStore` (optional): wraps `RedisSessionStore` with a very short L1 TTL (5 s) only if the user accepts per-worker delay; not recommended.

For the same reason `refresh_tokens` live in the DB/Redis, not the application cache.

### Refresh-token rotation (SuperTokens-style)

```php
class RefreshTokenRepository
{
    public function rotate(string $tokenValue): RefreshToken
    {
        return $this->db->transaction(function () use ($tokenValue) {
            $row = $this->db->table('refresh_tokens')
                ->where('token_hash', '=', hash('sha256', $tokenValue))
                ->whereNull('used_at')
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->forUpdate() // or UPDATE ... RETURNING
                ->first();

            if ($row === null) {
                // token already used or does not exist -> possible reuse
                $this->revokeFamilyByHash($tokenValue);
                throw new RevokedRefreshTokenException();
            }

            $this->db->table('refresh_tokens')
                ->where('id', $row['id'])
                ->update(['used_at' => now()]);

            $new = $this->issueForSession($row['session_id'], $row['family']);

            return $new;
        });
    }
}
```

The `used_at` update is guarded by the `WHERE used_at IS NULL`/`whereNull` condition. A `ChannelLock` on `refresh:{family}` protects the critical section in OpenSwoole coroutines.

### Plugin / strategy registry (Better Auth-style)

`AuthManager` keeps a strategy registry. Legacy `driver` keys map to built-in strategies for backward compatibility.

```php
interface AuthStrategy
{
    public function getName(): string;
    public function authenticate(array $credentials): Session;
    public function register(array $data): Authenticatable;
}

interface IdentityStrategy extends AuthStrategy
{
    public function redirect(array $state): string;
    public function callback(string $code, array $state): Session;
}
```

`AuthManager` resolves:

- `driver: 'session'` → `EmailPasswordStrategy` (or `SessionStrategy`).
- `driver: 'token'` → `AccessTokenStrategy`.
- `driver: 'api_key'` → `ApiKeyStrategy`.
- `driver: 'basic'` → `BasicAuthStrategy`.
- `driver: 'oidc'` → `OidcStrategy` (identity strategy).

Custom strategies register with:

```php
auth()->extend('magic-link', MagicLinkStrategy::class);
```

### Identity provider federation (Keycloak-style)

```php
interface IdentityProvider
{
    public function getName(): string;
    public function redirectUrl(string $state, ?string $codeChallenge): string;
    public function exchangeCode(string $code, string $codeVerifier): IdentityToken;
    public function resolveUser(IdentityToken $token): ?Authenticatable;
}

class IdentityBroker
{
    public function authenticate(string $provider, string $code, array $state): Session;
}
```

`IdentityBroker` flow:

1. On `/auth/{provider}/redirect`, generate PKCE verifier, store verifier+state+nonce in Redis with 5 min TTL, and redirect to the provider.
2. On `/auth/{provider}/callback`, read the verifier by state, exchange code for tokens, fetch `userinfo` or decode `id_token`, and create or link a local user.
3. `Identity` table stores `(provider, provider_user_id, user_id, data, linked_at)`.
4. Account linking: if `link_by_email` is true and the email is verified, link to an existing user; otherwise create a new user via `AuthUserManager`.

### `AuthUserManager` (user creation)

`UserProvider` is read-only by design. We add `AuthUserManager`:

```php
interface AuthUserManager
{
    public function create(array $data): Authenticatable;
    public function updatePassword(Authenticatable $user, string $password): void;
    public function findForLinking(string $email, string $provider): ?Authenticatable;
}
```

The default implementation writes to the `users` table and uses the ORM `Model` when `auth.providers.{name}.model` is set.

### Route integration

Existing routing already has `#[Authenticate]` and `#[Guard]`. We add:

```php
#[Controller('/admin', guards: [IsAdmin::class])]
class AdminController {}

#[Get('/posts/{post}')]
#[Authorize('update', Post::class)]  // policy gate on the resolved Post
public function update(#[CurrentUser] User $user, Post $post) { ... }

#[Post('/transfer')]
#[Authenticate('web')]
#[RequireMfa]
public function transfer(...) { ... }
```

New route helpers:

```php
$route->post('/login', [AuthController::class, 'login'])
      ->middleware([RateLimit::class]);

$route->group('/admin', function ($route) {
    // ...
}, ['middleware' => [Authenticate::class], 'guards' => [IsAdmin::class]]);
```

`#[CurrentUser]` is resolved by `HandlerInvoker`:

```php
if ($typeName === Authenticatable::class || is_subclass_of($typeName, Authenticatable::class)) {
    $user = auth()->user();
    if ($user === null && !$type->allowsNull()) {
        throw new AuthorizationException();
    }
    return $user;
}
```

### Multi-factor authentication (MFA)

```php
interface MfaFactor
{
    public function getName(): string;
    public function enroll(Authenticatable $user): MfaSetup;
    public function verify(Authenticatable $user, string $code): bool;
    public function disable(Authenticatable $user): void;
}
```

Enrollment stores encrypted secrets in `mfa_factors` (DB, never in `OpenSwoole\Table`).

Flow:

1. `Auth::login('email', [...])` returns a `Session` with `mfa_verified => false` if MFA is required.
2. Client posts `/auth/mfa/verify` with a TOTP/email code.
3. `MfaManager::verify($factor, $code)` succeeds.
4. `SessionManager::addClaim($session->id, 'mfa_verified', true)` updates the session. For stateless tokens, a new access token is issued. For stateful, the row is updated and the cookie is refreshed.
5. `#[RequireMfa]` checks `auth()->session()?->claims['mfa_verified']`.

### Cookie handling

`Response` needs a typed `cookie` helper:

```php
public function cookie(
    string $name,
    string $value,
    int $expires = 0,
    string $path = '/',
    ?string $domain = null,
    bool $secure = true,
    bool $httpOnly = true,
    string $sameSite = 'lax'
): self;
```

`OpenSwoole\Http\Response::setCookie` is called under the hood. `SessionGuard::login` sets the access-token cookie and a matching `csrf_token` cookie/header.

---

## Data model

### `users` (existing)

```sql
id, email, password, api_token, api_key, name, remember_token, created_at, updated_at
```

Add optional columns:

- `mfa_enabled` (bool)
- `email_verified_at` (timestamp, for OIDC linking)

### `sessions` (new)

```sql
id              VARCHAR(36) PRIMARY KEY
user_id         VARCHAR(255) NOT NULL
claims          JSON
anti_csrf       VARCHAR(255)
device          VARCHAR(255)
status          ENUM('active','revoked')
expires_at      BIGINT
created_at      BIGINT
```

### `refresh_tokens` (new)

```sql
id              BIGINT AUTO_INCREMENT PRIMARY KEY
session_id      VARCHAR(36)
family          VARCHAR(36)
parent          BIGINT NULL -- previous token id
token_hash      VARCHAR(64) UNIQUE
used_at         BIGINT NULL
revoked         BOOLEAN DEFAULT FALSE
expires_at      BIGINT
created_at      BIGINT
```

### `identities` (new)

```sql
id                BIGINT AUTO_INCREMENT PRIMARY KEY
user_id           VARCHAR(255)
provider          VARCHAR(64)
provider_user_id  VARCHAR(255)
email             VARCHAR(255)
data              JSON
linked_at         BIGINT
UNIQUE (provider, provider_user_id)
```

### `mfa_factors` (new)

```sql
id          BIGINT AUTO_INCREMENT PRIMARY KEY
user_id     VARCHAR(255)
factor      VARCHAR(32) -- 'totp', 'email_otp'
secret      TEXT       -- encrypted
enabled     BOOLEAN
created_at  BIGINT
```

### `api_tokens` / `personal_access_tokens` (new)

```sql
id          BIGINT AUTO_INCREMENT PRIMARY KEY
user_id     VARCHAR(255)
name        VARCHAR(255)
token_hash  VARCHAR(64) UNIQUE
scopes      JSON
expires_at  BIGINT
created_at  BIGINT
```

`ApiKeyGuard` can be migrated to read from this table (hashed) and the legacy `api_keys` table (plain) via config.

---

## Components

| Component | Responsibility |
|-----------|----------------|
| `Auth` / `AuthManager` | Public facade, strategy registry, guard resolution. |
| `AuthStrategies` | Plugin registry. |
| `AuthStrategy` / `IdentityStrategy` | Per-method authentication (email, OIDC, API key, basic). |
| `SessionManager` | Create, verify, refresh, revoke, update-claim. |
| `SessionStore` / `RedisSessionStore` / `DatabaseSessionStore` | Cross-worker session persistence. |
| `AccessTokenManager` | Sign/verify compact HMAC access tokens. |
| `RefreshTokenRepository` | Atomic refresh-token rotation. |
| `IdentityBroker` / `IdentityProvider` / `IdentityToken` | OIDC federation and account linking. |
| `AuthUserManager` | Create/update local users for registration and linking. |
| `MfaManager` / `MfaFactor` | MFA enrollment, verification, disabling. |
| `StatefulGuard` (contract) | `login` / `logout` for cookie/session guards. |
| `SessionGuard` | Cookie-based stateful guard; implements `StatefulGuard`. |
| `AccessTokenGuard` | Bearer access-token guard; stateless or stateful. |
| `ApiKeyGuard` | Long-lived hashed API key guard. |
| `BasicAuthGuard` | Per-request basic auth. |
| `AuthRouteGuard`, `RoleGuard`, `ScopeGuard` | `Routing\Contracts\Guard` adapters. |
| `#[CurrentUser]`, `#[Authorize]`, `#[RequireMfa]` | Routing attributes. |
| `VerifyCsrfToken` middleware | Anti-CSRF for cookie sessions. |

---

## Security checklist

- [ ] Access tokens short-lived (15 min default).
- [ ] Refresh tokens long-lived, single-use, family-bound, with reuse detection.
- [ ] Stateful sessions checked against `sessions.status`.
- [ ] Stateless sessions use short TTL; immediate logout requires `revokeAllSessions` + client discarding token.
- [ ] Cookies are `HttpOnly`, `Secure` in production, `SameSite=Lax` or `Strict`.
- [ ] Anti-CSRF token for cookie-based `POST`/`PUT`/`PATCH`/`DELETE`.
- [ ] Refresh and session storage use Redis/DB, not `HybridStore` L1.
- [ ] OIDC state + PKCE verifier stored server-side with TTL, not in the client.
- [ ] API keys stored as hashes; the raw key is shown only once at creation.
- [ ] Passwords rehashed automatically if `password_needs_rehash`.
- [ ] MFA secrets encrypted at rest.
- [ ] Guard objects are stateless; per-request state in `Context`.

---

## Suggested implementation phases

### Phase 1 — Core session/token manager (foundation)

- `Session`, `AccessToken`, `RefreshToken` value objects (JSON-safe).
- `SessionStore` interface + `RedisSessionStore` + `DatabaseSessionStore`.
- `SessionManager` with create/verify/refresh/revoke/addClaim.
- `AccessTokenManager` (HMAC-SHA256 compact token).
- `RefreshTokenRepository` with atomic `used_at` rotation.
- `StatefulGuard` contract.
- Rewrite `SessionGuard` to be stateless and cookie-based.
- Add `Response::cookie()` helper.
- Add `sessions` + `refresh_tokens` migrations.
- Unit/Redis integration tests.

### Phase 2 — Backward-compatible strategy system

- `AuthStrategy` / `IdentityStrategy` contracts and `AuthStrategies` registry.
- `EmailPasswordStrategy`, `AccessTokenStrategy`, `ApiKeyStrategy`, `BasicAuthStrategy`.
- `AuthManager` keeps legacy `driver` resolution by mapping to strategies.
- `Auth` facade methods: `register`, `login`, `logout`, `session`, `user`, `refreshSession`, `issueApiToken`, `revokeSession`.
- `AuthUserManager` for user creation.
- Update `config/auth.php` with `strategies` section.

### Phase 3 — OIDC / identity provider federation

- `IdentityProvider`, `IdentityToken`, `IdentityBroker`.
- `OidcProvider` with discovery, PKCE, `OpenSwoole\Coroutine\Http\Client` token exchange.
- `GoogleProvider`, `GitHubProvider`, `KeycloakProvider` examples.
- `identities` migration and account linking.
- `/auth/{provider}/redirect` and `/auth/{provider}/callback` scaffolded routes or CLI generator.

### Phase 4 — Route and authorization integration

- `#[CurrentUser]` parameter resolution in `HandlerInvoker`.
- `#[Authorize]` policy attribute.
- `#[RequireMfa]`.
- `AuthRouteGuard`, `RoleGuard`, `ScopeGuard` implementing `Routing\Contracts\Guard`.
- `VerifyCsrfToken` middleware.
- `RouteDefinition::guard()` and `GroupBuilder` guard support.

### Phase 5 — MFA

- `MfaManager`, `MfaFactor`.
- `TotpFactor` (time-based) and `EmailOtpFactor`.
- `mfa_factors` migration.
- `/auth/mfa/enroll` and `/auth/mfa/verify` endpoints.

### Phase 6 — OpenSwoole hardening, tests, docs

- Coroutine stress tests for login/logout/refresh race conditions.
- Test that logout on worker A rejects the token on worker B within one request.
- OIDC E2E with a local Keycloak/docker or mocked HTTP server.
- `auth:clear-sessions` CLI command.
- Rewrite `docs/authentication.md` and add `docs/authorization.md`.
- Architecture tests ensuring `Guard` classes have no per-request properties.

---

## Public API preview

```php
use TondbadSwoole\Auth\Facades\Auth;
use TondbadSwoole\Routing\Attributes\Controller;
use TondbadSwoole\Routing\Attributes\Get;
use TondbadSwoole\Routing\Attributes\Post;
use TondbadSwoole\Routing\Attributes\Guard;
use TondbadSwoole\Routing\Attributes\Authorize;
use TondbadSwoole\Http\Attributes\CurrentUser;

#[Controller('/auth')]
class AuthController
{
    #[Post('/sign-in/email')]
    public function signIn(#[Body] SignInDto $dto, Response $response): void
    {
        $session = Auth::login('email', $dto->toArray());

        if ($session === null) {
            $response->error('Invalid credentials', 401);
            return;
        }

        $response->cookie('session_id', $session->accessToken->value, $session->accessToken->expiresAt);
        $response->json([
            'access'  => $session->accessToken->value,
            'refresh' => $session->refreshToken?->value,
        ]);
    }
}

#[Controller('/admin', guards: [IsAdmin::class])]
class AdminController
{
    #[Get('/dashboard')]
    public function dashboard(#[CurrentUser] User $user): array
    {
        return ['user' => $user->toArray()];
    }
}
```

---

## What we still do not build

- A full Keycloak replacement (no realm admin UI, no full SAML spec).
- A Better Auth port (we keep Tondbād’s guard/provider style and only adopt the strategy/session ideas).
- Frontend SDKs (we expose HTTP/gRPC auth endpoints).
- Magic links, passwordless, or WebAuthn out of the box (the architecture supports them via `MfaFactor` / `AuthStrategy` plugins, but they are separate phases).

This is a bullet-proof, OpenSwoole-native auth architecture that integrates with the existing routing guards, `Gate`, cache, and ORM while fixing the per-worker L1 and coroutine-state bugs in the current `SessionGuard`.
