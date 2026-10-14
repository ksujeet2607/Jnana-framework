# Installation and application paths

## Install

In a new or existing PHP application, install the package through Composer:

```bash
composer require jnana/framework
```

The framework requires PHP `^8.1` and the dependencies listed in the package's `composer.json`. Check the exact dependency constraints there before upgrading PHP or Symfony/Doctrine packages.

## Separate package and application roots

A typical application layout is:

```text
my-app/
├── app/
│   ├── Controllers/
│   ├── Entities/
│   ├── Middlewares/
│   ├── Providers/
│   ├── Services/
│   └── Validators/
├── config/
├── public/
│   └── views/
├── storage/
│   └── framework/
│       ├── logs/
│       └── temp/cache/
├── vendor/
│   └── jnana/framework/   # framework package installed by Composer
├── .env
├── composer.json
├── index.php
└── router.php
```

Actual application directories can differ, but the defaults above are the conventions used by the supplied application skeleton and path service.

## `ApplicationPaths`

`Core\\ApplicationPaths` is the canonical path context. Initialize it early, before any helper or service calls `ApplicationPaths::instance()`:

```php
$applicationRoot = dirname(__DIR__); // from app/Bootstrap.php
$paths = new Core\ApplicationPaths($applicationRoot);
```

The constructor validates that the application root exists and records application, framework and vendor roots. The application bootstrap in `jnana/jnana` is the reference integration.

Available instance methods:

- `applicationRoot()` — absolute application root.
- `frameworkRoot()` — root of the installed framework package.
- `vendorRoot()` — application's Composer vendor directory.
- `app($path = '')` — path relative to the application root. Despite the method name, this is the general application-root joiner.
- `config($path = '')`, `storage($path = '')`, `public($path = '')` — paths under those application directories.
- `framework($path = '')`, `vendor($path = '')` — paths under framework and vendor roots.
- `namespaceDirectory($namespace)` — maps a configured namespace prefix (default `App\\`) to its application filesystem directory.

The constructor accepts optional framework root, vendor root and namespace-prefix mapping. Namespace mapping values are relative to the application root.

## Global path helpers

Helpers are loaded by Composer's `autoload.files` entry. Use them only after bootstrap has initialized `ApplicationPaths`:

```php
base_path('composer.json');
app_path('Controllers/WelcomeController.php');
config_path('Config.php');
storage_path('framework/logs/app.log');
public_path('assets/app.css');
framework_path('jnana/Http/Request.php');
vendor_path('autoload.php');
view_path('welcome.php');
cache_path('templates');
```

`base_path()` is the project root, `app_path()` points into the project's `app/` directory, and `view_path()` points into `public/views/` by default. These helpers return filesystem paths; they do not create files or directories.

## Initialization lifecycle

`ApplicationPaths::instance()` throws `LogicException` if initialization has not occurred. This is deliberate: a helper cannot safely guess which application root to use. Ensure every HTTP and CLI entry point loads Composer autoload and initializes the application bootstrap before using framework path helpers.

Avoid creating a second `ApplicationPaths` with a different root during one request. `ContainerFactory` registers the path object in the DI container and initializes the shared instance for framework services.

## Working-directory independence

Application paths must continue to work when PHP is launched outside the project directory. Build paths from the explicit application root, not `getcwd()`. For example, run a smoke test from another directory and verify configuration, logs, views and route discovery still resolve.
