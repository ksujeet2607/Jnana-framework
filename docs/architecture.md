# Architecture

## Purpose and package boundary

`jnana/framework` is the reusable runtime. It owns HTTP abstractions, routing, middleware dispatch, dependency-injection integration, configuration loading, template rendering, security primitives, and framework helpers. It must not depend on application-specific entities, services, controllers, or configuration files.

The consuming project (`jnana/jnana` or another application) owns domain code and runtime configuration. Composer maps `Core\\` to `core/` and `Library\\` to `library/`; the application maps `App\\` to its own `app/` directory.

## Main areas

- `core/`: application lifecycle (`AppManager`), route discovery (`Router`), config loading (`ConfigManager`), DI setup (`ContainerFactory`), path context (`ApplicationPaths`), error rendering, loading and maintenance management.
- `library/Http/`: request, response, redirect response, uploaded-file abstraction, and framework base controller.
- `library/Routes/`: route attributes (`Get`, `Post`, `RouteGroup`, `Route`).
- `library/Middleware/`: middleware contract, dispatcher, built-in middleware.
- `library/Security/`: authentication strategies, guards, password hashing, authorization and security services.
- `library/Database/`: database abstractions and Doctrine/DBAL adapters, including null implementations.
- `library/Template/`, `library/Utilities/`, `library/Helpers/`, `library/Facades/`: view compilation/rendering, common utilities, procedural helpers and facade access.
- `core/CLI/Commands/`: bundled console command implementations. Registering/executing commands is the responsibility of the application's CLI entry point.

## Request lifecycle

The normal HTTP entry point is the application's `index.php`:

1. Composer autoload is loaded.
2. The application bootstrap initializes `ApplicationPaths` using the application root.
3. Environment variables, debugger/logging, configuration and the DI container are initialized.
4. `AppManager` constructs `Router`, loads global middleware and discovers routes.
5. A `Request` and response are prepared for the incoming request.
6. Global pre-controller middleware runs.
7. The router resolves method and URI to a controller/action and route parameters.
8. The controller is obtained from the DI container; lifecycle hooks run when implemented.
9. Route middleware and guards run, then the controller action executes.
10. Global post-controller middleware runs and the response is sent.

A route-not-found, method-not-allowed, authorization, CSRF or application exception may interrupt this flow and be handled by the framework's error path.

## Dependency direction

Allowed: application code depends on framework contracts and services.

Not allowed: framework core imports application concrete classes such as `App\\Entities\\User` or `App\\Services\\...`. Where application behavior is required, define a contract under `Library\\Contracts` or use an application-owned DI binding. `AppContextInterface` and `Authenticatable` are examples of this boundary.

## Compatibility rules for maintainers

- Treat public classes, method signatures, route attributes, helper names, and DI contracts as APIs.
- Avoid relying on the process working directory (`getcwd()`) for application files.
- Do not derive application paths from the framework's `__DIR__`.
- Keep paths deterministic through `ApplicationPaths`.
- Do not make a framework package require an application's `App\\` classes.
- If adding a Composer dependency, verify it is used by framework code and declared in `composer.json`.
- Add tests for both the framework package and a consuming application where practical.
