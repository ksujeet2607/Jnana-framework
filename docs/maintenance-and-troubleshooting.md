# Maintenance and troubleshooting

## Before changing framework internals

1. Identify whether the behavior belongs in the reusable framework or in the application. Domain rules belong in the application.
2. Read the relevant interface and all implementations before changing a contract.
3. Keep changes backward-compatible where possible; route attributes and helpers are public APIs.
4. Add/update tests and documentation alongside behavior changes.
5. Test with a consuming application, not only from inside the framework repository.
6. Verify paths with a process working directory outside the project root.

## Suggested checks

```bash
composer validate
composer dump-autoload
vendor/bin/phpunit
```

Run only commands that exist in the checkout; the package declares PHPUnit as a development dependency, but a test suite may not be included in a packaged archive. Also run `php -l` on changed PHP files. For integration checks, install the framework in a clean skeleton application and test HTTP routes, middleware, DI bindings, views and database setup.

## Common errors

### `ApplicationPaths has not been initialized`

A path helper was called before bootstrap. Load Composer autoload and run the application bootstrap before calling `base_path()`, `config_path()`, `view_path()` or `ApplicationPaths::instance()`. Do not suppress the exception or infer the root from `getcwd()`.

### Configuration directory not found

Confirm `ApplicationPaths` received the absolute consuming-project root, not `vendor/jnana/framework`. Confirm `config/Config.php` exists and is readable.

### `Namespace directory not found`

Check the configured route namespace and its mapping. The default `App\\` prefix maps to `app/`; `App\\Controllers` maps to `app/Controllers`. Check case sensitivity on Linux.

### Route not found or method not allowed

Confirm the route attribute path, HTTP method, configured namespace, class autoloading and server rewrite rules. Query strings do not form part of the route path.

### DI cannot resolve an interface

Register a binding in `config/Dependencies.php`, implement required framework contracts, and verify the concrete class is PSR-4 autoloadable.

### Doctrine metadata or proxy failures

Confirm database config is non-empty, the entity mapping uses supported Doctrine attributes, the configured entity directory exists, and `app/Proxies` is writable. Check that the database driver and schema match the configured connection.

### View not found

Confirm the view exists under `public/views` and pass the template name in the format expected by the installed renderer. Do not pass a public URL where a template name is expected.

### Logs/cache cannot be written

Ensure `storage/framework/logs` and `storage/framework/temp/cache` exist and are writable by the PHP process. Avoid broad world-writable permissions in production; assign correct owner/group permissions.

## Release process

- Update package version/tag rather than attempting to mutate an already published immutable version.
- Review `composer.json` constraints and changelog/release notes.
- Run static checks and a clean-install integration test.
- Update README and docs for changed public behavior.
- Verify the published archive contains the expected source and documentation.
