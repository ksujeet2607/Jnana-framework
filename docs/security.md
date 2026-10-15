# Jnana Security

## CSRF token expiry and refresh

`SecurityService` creates session-bound CSRF tokens. The default token lifetime is 7,200 seconds and can be changed with `config/Security.php`:

```php
'csrf_token_lifetime' => (int) (getenv('CSRF_TOKEN_LIFETIME') ?: 7200),
'csrf_exclusion_methods' => ['GET', 'HEAD', 'OPTIONS'],
```

State-changing requests must include the token in the `easy-csrf-token` form field or `X-CSRF-Token` header. A stale token produces HTTP `419` with `error: csrf_token_expired`; a token mismatch produces HTTP `403` with `error: csrf_validation_failed`. Both are rejected before the controller runs.

The application skeleton provides `GET /security/csrf-token`. It refreshes the token for the current session and returns `Cache-Control: no-store`. This endpoint does not re-authenticate a user or restore an expired login session.

In a shared layout, add the token meta tag and script. The URL below assumes the application root is the web root; if your web server uses `public/` as its document root, use `/js/jnana-security.js` instead:

```php
<head>
    <?php csrf_meta(); ?>
    <script src="/public/js/jnana-security.js" defer></script>
</head>
```

For AJAX code, use the `X-CSRF-Token` header and listen for the recovery event:

```javascript
document.addEventListener('jnana:csrf-expired', function (event) {
    // The token has been refreshed. The original write was NOT replayed.
    // Tell the user to review and submit the form again.
    alert('Your form session expired. Please review and submit again.');
});

fetch('/example/update', {
    method: 'POST',
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': window.JnanaSecurity.getToken()
    },
    body: JSON.stringify({ example: true })
});
```

The helper refreshes the token when a fetch response has status 419 and updates the meta tag and hidden form inputs. It deliberately does not automatically replay a state-changing request: automatic replay can duplicate payments or other writes. A client may retry only after confirming the operation is safe to retry or after adding an idempotency key.

## Session cookie and timeout settings

`SessionManager` enables PHP strict session mode, cookie-only sessions, and disables URL-based session IDs. Cookie defaults are `HttpOnly`, `SameSite=Lax`, and `Secure` when HTTPS is detected. Configure explicitly in `.env` when needed:

```dotenv
SESSION_COOKIE_SECURE=1
SESSION_COOKIE_HTTPONLY=1
SESSION_COOKIE_SAMESITE=Lax
SESSION_COOKIE_DOMAIN=
SESSION_IDLE_TIMEOUT=1800
SESSION_ABSOLUTE_TIMEOUT=28800
CSRF_TOKEN_LIFETIME=7200
```

The idle and absolute timeouts are disabled when set to `0` (the compatibility default). Enable them according to the application's authentication and user experience requirements. Set `SESSION_COOKIE_SECURE=1` in production HTTPS deployments. Do not use `SameSite=None` without HTTPS.

Regenerate the session ID after successful authentication and privilege changes using `app(\\Jnana\\Session\\SessionManager::class)->regenerate()`. Destroy the session on logout. Do not trust forwarded IP headers unless the application is behind a correctly configured trusted proxy.

## CSRF exclusions

Only exclude routes when there is a specific, documented need. External webhooks should use provider signature verification; do not exempt them from protection without adding an alternative authenticity check. Safe HTTP methods should not modify application state.

## Security response headers

The application skeleton enables `SecurityHeadersMiddleware` globally. It sets `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, and a restrictive `Permissions-Policy`. HSTS is opt-in through `SECURITY_HSTS=1` and is emitted only when the request is detected as HTTPS. Deployments behind a reverse proxy must configure HTTPS detection correctly. Content Security Policy is not forced globally because existing applications may rely on inline scripts; configure a CSP after auditing the application's scripts, styles, and external resources.
