# Database and security

## Database abstractions

The framework exposes `Library\\Database\\DatabaseInterface` and implementations including `Database` and `NullDatabase`. The container selects a real database adapter when database configuration is present; otherwise the skeleton can use a null database so the welcome page works without a database connection.

When a database is configured, Doctrine ORM's `EntityManagerInterface` is built with attribute metadata from the application's `app/Entities` directory. Proxy classes are written to `app/Proxies` under the `App\\Proxies` namespace. The directory must be writable. Database credentials belong in environment variables or an equivalent secret store.

The null entity manager is a bootstrapping convenience, not a replacement for a real persistence layer. Database-dependent features must explicitly handle the absence of a configured database.

## Authentication strategies

The framework defines `AuthStrategyInterface`, `FileAuthStrategy` and `DatabaseAuthStrategy`, coordinated through `AuthManager`. The application configuration selects `auth_strategy` as either `file` or `database`. The skeleton defaults to file authentication using `config/users.json`, allowing a new installation to run without database setup.

For database authentication, configure the database and ensure the user entity/table and authentication strategy agree. Do not use demo credentials in production. Store password hashes, never plaintext passwords.

## Application identity contracts

The framework does not own the application's User entity. Implement `Library\\Contracts\\Authenticatable` on the application user model and provide an `AppContextInterface` implementation that returns the current authenticated user. Bind `AppContextInterface` to that implementation in `config/Dependencies.php`.

This keeps the framework decoupled from the application's entity structure.

## Guards and authorization

Guards are resolved through `GuardResolver` and the framework's guard/authorization abstractions. Apply authorization checks on the server for every protected action. Hiding a link or button in the UI is not authorization. Verify both authentication and permission for access to sensitive records and operations.

The exact guard attribute/API should be confirmed against the installed source before copying examples from another version; use the framework's `Guard` and `GuardInterface` definitions as the API contract.

## CSRF and input handling

The default application config enables `CsrfMiddleware` as global pre-controller middleware. Keep CSRF protection enabled for state-changing browser requests that rely on cookies/sessions. Do not disable it globally to fix a single failing form; correct the token generation and submission flow instead.

Sanitize for context where appropriate, validate business rules server-side, and use parameterized database access. Output escaping is separate from input validation. Avoid logging passwords, session tokens, CSRF tokens or full sensitive request bodies.

## Operational security checklist

- Set `APP_ENV` to production in production deployments and disable verbose route diagnostics.
- Protect `.env`, logs, cache and configuration files from public web access.
- Ensure the web root points to the intended public directory/entry point and server rewrite rules are correct.
- Use strong unique credentials and rotate exposed secrets.
- Keep dependencies patched and review Composer audit output.
- Confirm that file authentication's user JSON is not publicly downloadable.
