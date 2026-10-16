# Jnana Security — Phase 2

This phase adds reusable login rate limiting and tightens authentication session lifecycle behavior.

## 1. Apply rate limiting to login routes

`LoginRateLimitMiddleware` is intentionally **not global**. Attach it only to login submission routes, so ordinary forms and authenticated actions are not throttled as login attempts.

```php
use Jnana\Middleware\LoginRateLimitMiddleware;
use Jnana\Routes\Post;

#[Post('/login', [LoginRateLimitMiddleware::class])]
public function login(): Response
{
    // Validate credentials and call AuthManager::authenticate(...).
}
```

The middleware uses `REMOTE_ADDR`; it does not trust `X-Forwarded-For`. It limits both requests from an IP and repeated attempts against the same identifier from that IP. It responds with HTTP `429`, a `Retry-After` header, and a JSON error payload. The default is five attempts per 900-second fixed window.

Configure with `.env`:

```dotenv
LOGIN_RATE_LIMIT_ENABLED=1
LOGIN_RATE_LIMIT_MAX_ATTEMPTS=5
LOGIN_RATE_LIMIT_WINDOW_SECONDS=900
LOGIN_IDENTIFIER_FIELD=username
SECURITY_RATE_LIMIT_DIR=
```

The file-backed limiter is suitable for a single server. If the application runs across multiple hosts or containers, replace it with an atomic shared store (for example Redis). Ensure the configured directory is writable by the PHP process and is not web-accessible. The limiter fails closed if it cannot create or update its state.

## 2. Authentication session lifecycle

`AuthManager::authenticate()` regenerates the session identifier after successful authentication. `logout()` removes the authenticated user and rotates the identifier again. Do not call `session_regenerate_id()` after output has started; authentication should happen before the response is emitted.

## 3. Database password rehash fix

`DatabaseAuthStrategy` now verifies the submitted password before rehashing. This ordering is security-critical: never write a new hash based on an unverified submitted password. Authentication table/column identifiers supplied through configuration are validated before interpolation into SQL.

## 4. Configuration

`config/Security.php` now includes `rate_limit.login` defaults. Keep `Security` as the configuration group name when reading it through `ConfigManager`.

## 5. Verification checklist

- [ ] Attach middleware to the actual login POST route.
- [ ] Confirm a correct login succeeds and rotates the session ID.
- [ ] Confirm logout clears authentication and rotates the session ID.
- [ ] Submit more than the configured number of login attempts and verify HTTP 429 plus `Retry-After`.
- [ ] Verify a failed password attempt never changes the database password hash.
- [ ] Test on the deployment server and, for multi-node deployments, use a shared rate-limit backend.
