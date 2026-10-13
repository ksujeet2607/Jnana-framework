# Configuration and dependency injection

## Configuration loading

`Core\\ConfigManager` loads PHP files from the application's `config/` directory. `Config.php` is merged into the top-level configuration array. Other PHP files are loaded under a key matching the filename: for example, `Security.php` becomes `$config->get('Security')` and `Dependencies.php` is read by the container factory for bindings/providers.

Each configuration file should return an array, except `Config.php` may return `null` to indicate no root values. Configuration is loaded at construction and validated early. The current validation requires a non-empty `routes` array, an `auth_strategy` of `file` or `database`, an array of DI definitions, and an array for `db` when supplied.

Read values using:

```php
$config->get('db.host');
$config->get('db.host', 'localhost');
$config->getRoutes();
$config->getDefinitions();
$config->getDatabaseConfig();
$config->all();
```

Dot notation is supported by `get()`. Dynamic property access is retained for top-level values, e.g. `$config->routes`, but prefer `get()` for explicit defaults and nested keys.

## Environment variables

The framework does not decide how `.env` is loaded; the application bootstrap does. In the skeleton, `vlucas/phpdotenv` loads `.env` before the container is built. Environment variables are then read by configuration files. Do not put secrets in version-controlled PHP configuration or `.env.example`.

## DI container

`Core\\ContainerFactory::create(ConfigManager $config, LoggerInterface $logger)` builds a PHP-DI container with autowiring and attribute injection enabled. It registers core services, auth strategy, entity manager, cache, manual bindings, service providers and validators.

### Manual interface bindings

In `config/Dependencies.php`:

```php
return [
    'bindings' => [
        App\Contracts\ClockInterface::class => DI\autowire(App\Services\SystemClock::class),
    ],
    'providers' => [],
];
```

Use manual bindings when an interface cannot be inferred or a service needs an explicit implementation. In real projects, import `DI\\autowire` or use the fully qualified `\\DI\\autowire` helper as appropriate to the file's namespace.

### Service providers

Providers implement `Library\\Contracts\\ServiceProviderInterface`. Providers listed in `definitions['providers']` and classes discovered in `app/Providers` are combined. A provider is instantiated and its `register()` method receives the `DI\\ContainerBuilder`. Keep provider constructors simple because the current discovery path instantiates providers without constructor arguments.

### Validators

Concrete, autoloadable classes under `app/Validators` are discovered and registered with autowiring. Keep the PSR-4 namespace aligned with the directory (`App\\Validators`). Missing directories are logged and skipped; unresolved class files generate warnings.

### Controller injection

The framework base controller uses PHP-DI's `#[Inject]` for common dependencies such as Request, Response, SessionManager, LoggerInterface, DatabaseInterface, optional EntityManagerInterface, and `AppContextInterface`. The application must bind application contracts such as `AppContextInterface` to application-owned implementations.

## Caching

The container registers Symfony's filesystem cache adapter through `CacheInterface`. Template caching has its own framework classes and application storage conventions. Ensure the configured cache directories are writable by the PHP process and clear generated cache after changing compiler behavior.

## Failure modes

- `Invalid routes configuration`: configure at least one route namespace.
- `Auth strategy not specified`: set `auth_strategy` in `Config.php`.
- `Configuration directory not found`: initialize the correct application root and ensure `config/` exists.
- Invalid configuration return type: return an array from each config file.
- DI `NotFoundException`: check autoloading and explicit interface bindings.
