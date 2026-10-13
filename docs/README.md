# Jnana Framework Documentation

This documentation is for maintainers of `jnana/framework` and developers who need to understand or extend its internals.

## Documentation map

- [Architecture](architecture.md) — package boundaries, request lifecycle, and design rules.
- [Installation and application paths](installation-and-paths.md) — Composer installation and `ApplicationPaths`.
- [Routing and middleware](routing-and-middleware.md) — attribute routes, route discovery, and middleware order.
- [Configuration and dependency injection](configuration-and-di.md) — `ConfigManager`, definitions, providers, and validators.
- [HTTP, controllers, and views](http-controllers-and-views.md) — request/response conventions and template rendering.
- [Database and security](database-and-security.md) — Doctrine integration, auth strategies, guards, CSRF, and security boundaries.
- [Maintenance and troubleshooting](maintenance-and-troubleshooting.md) — safe change process and common failures.

## Source of truth

The implementation is the source of truth. This guide describes the code included with this package version. When a public API changes, update the relevant guide and examples in the same pull request.
