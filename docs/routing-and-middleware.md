# Routing and middleware

## Route discovery

The router scans configured controller namespaces. In the standard skeleton, `config/Config.php` configures `App\\Controllers`, which maps to `app/Controllers`. The class must be autoloadable by Composer and its PHP file must follow PSR-4 namespace/path conventions.

Routes are declared using PHP attributes on public controller methods:

```php
namespace App\Controllers;

use Jnana\Routes\Get;
use Jnana\Routes\Post;

final class ReportController extends Foundation
{
    #[Get('/reports')]
    public function index()
    {
        return $this->render('reports/index', []);
    }

    #[Post('/reports/export')]
    public function export()
    {
        return $this->text('Export queued');
    }
}
```

`Get` registers a GET route and is repeatable; `Post` registers a POST route. `Route` is the base attribute and stores request method, path and optional middleware definitions.

## Route groups

Use `#[RouteGroup]` on a controller to add a prefix and middleware to every route on that controller:

```php
use Jnana\Routes\RouteGroup;

#[RouteGroup(prefix: '/admin', middlewares: [AdminMiddleware::class])]
final class ReportController extends BaseController
{
    #[Get('/reports')]
    public function index() { /* ... */ }
}
```

The route path becomes `/admin/reports`. Prefix normalization adds a leading slash and removes a trailing slash.

## Middleware attributes

`Library\\Middleware\\Middleware` can be attached at controller or method level; route attributes can also receive middleware as their constructor argument. Middleware is combined in this order:

1. controller-level middleware,
2. route-group middleware,
3. method-level middleware,
4. route-attribute middleware.

Use middleware class names or `[MiddlewareClass::class, ['option' => 'value']]` definitions. Middleware classes must be resolvable by the DI container and implement `MiddlewareInterface`.

```php
use Jnana\Http\Request;
use Jnana\Http\Response;
use Jnana\Middleware\MiddlewareInterface;

final class ExampleMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Response $response, callable $next): Response
    {
        // Pre-processing goes here.
        $response = $next($request, $response);
        // Post-processing goes here.
        return $response;
    }
}
```

Always return a `Library\\Http\\Response`. Do not assume a PSR-7 response; Jnana uses its own Request/Response classes.

## Global middleware

Configure global middleware in `config/Config.php` using:

- `pre_controller_middlewares`: run before route resolution/controller execution.
- `post_controller_middlewares`: run after the controller action and lifecycle hook.

The default skeleton uses maintenance and CSRF middleware before the controller and clears old input after it. Middleware behavior and order should be reviewed when adding authentication, caching or request mutation.

## Route parameters and response contract

The router passes matched route parameters to the controller action in the order returned by FastRoute. Keep action arguments aligned with route placeholders and test them with real requests. Controller actions should return a `Response` or a value that the application intentionally supports coercing to a response; returning a `Response` is the clearest convention.

## Troubleshooting route discovery

- Confirm the namespace is listed in `config/Config.php` under `routes`.
- Confirm the namespace prefix maps to the correct directory in `ApplicationPaths`.
- Confirm Composer's PSR-4 mapping and PHP namespace match the file path.
- Check spelling/case of attribute paths and HTTP methods.
- Confirm the controller extends the application's `Foundation` and that dependencies can be resolved.
- Inspect application logs for route registration or middleware exceptions.
