# Jnana Security — Phase 4

## Correlation IDs and safe errors

Jnana generates a random 128-bit correlation ID for each HTTP request, sends it in `X-Correlation-ID`, and includes it in request/error logs. Unhandled exceptions return a generic HTML error page or JSON payload in production, including the correlation ID for support. Exception class and stack trace remain in server logs. The correlation ID is diagnostic metadata, not a secret or authorization credential.

Custom `errors/{status}` views receive `correlationId`. The `exception` variable is populated only when `APP_ENV=development`; production views receive `null`. Custom error templates should still avoid rendering exception details.

## Security audit logger

Resolve `Jnana\Security\SecurityAuditLogger` through the DI container and call `record('domain.event', $context, $request)` for relevant events. It redacts common secret-bearing context keys and removes CR/LF from scalar strings to reduce log injection risk. Do not pass request bodies, passwords, tokens, session cookies, or secrets into audit context. The framework currently records authentication success/failure/logout, authorization denials, CSRF rejection, upload decisions, and unhandled exceptions.

## Secure file uploads

Use `Jnana\Security\SecureFileUploadService` instead of moving user uploads directly into public directories. It validates upload status, file size, MIME type detected with PHP `finfo`, generates a random server-side filename, and writes under the application's `storage/` directory. It returns a relative storage path and metadata; persist the relative path in your database and serve files through an authorized download controller.

The application config `config/Uploads.php` controls the MIME allowlist and maximum size. Defaults allow JPEG, PNG, PDF, and plain text up to 5 MiB. Ensure PHP's `fileinfo` extension is enabled and align `upload_max_filesize` and `post_max_size` with the application limit. The upload route must still require appropriate authentication, authorization, and CSRF validation. For higher-risk file types or public uploads, add malware scanning and content-specific validation.
