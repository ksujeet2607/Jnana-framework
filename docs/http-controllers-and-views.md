# HTTP, controllers, and views

## Request and response types

Use `Library\\Http\\Request`, `Library\\Http\\Response` and `Library\\Http\\RedirectResponse`. They are Jnana abstractions, not PSR-7 objects. The request is created for each incoming HTTP request and registered in the container. The response stores status, headers and body and is sent by the framework's request lifecycle.

## Controller base class

Application controllers should normally extend an application-owned base controller, which itself extends `Library\\Http\\Controllers\\BaseController`:

```php
namespace App\Controllers;

use Library\Http\Controllers\BaseController as FrameworkBaseController;

abstract class BaseController extends FrameworkBaseController
{
}
```

This indirection gives applications one place for app-specific behavior without modifying the framework package. The framework base controller provides helpers such as `render()`, `text()`, `json()`, `redirect()` and `noContent()` plus access to injected request, response, session, logger, database, entity manager and application context services.

Use the provided methods instead of manually constructing HTTP output when possible:

```php
return $this->json(['ok' => true]);
return $this->text('Not found', 404);
return $this->redirect('/login');
return $this->render('welcome', ['title' => 'Welcome']);
```

The exact visibility of helpers follows the source class; for example, `text()` and `noContent()` are protected in the framework base controller.

## Lifecycle hooks

The base controller implements `ControllerLifecycle`. Override `onBeforeAction(Request $request)` for per-controller pre-action work and `onAfterAction(Request $request, Response $response): Response` for response changes. Do not override the final wrapper methods `beforeAction()` and `afterAction()`.

## View rendering

The default application skeleton stores PHP views under `public/views`. `render('welcome')` resolves a view through the framework's view facade/renderer and returns a response. Pass data as an associative array; keys become the view's local variables according to the renderer implementation.

Use `view_path()` to build a filesystem path to a view, not a public URL. Escape untrusted values at output time (e.g. `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`). Do not assume view templates are safe for arbitrary raw HTML.

## URL and filesystem path distinction

`public_path('assets/app.css')` is a filesystem path. A URL helper creates URL values for links. Never emit an absolute server path into HTML or use a filesystem path as an href.

## Sessions, forms and uploads

Use `SessionManager`/session helpers for session state and the request abstraction for incoming data. Validate all request data server-side. Uploaded files should be handled through the framework's uploaded-file abstraction and application-specific storage policy; do not trust client filenames or MIME declarations.

## Error handling

Framework exceptions include route-not-found, method-not-allowed, unauthorized, forbidden, CSRF validation, view-not-found and generic HTTP exceptions. Keep user-facing error messages safe; log diagnostic context server-side. Development mode may expose richer diagnostics, so production deployments should use production settings and protect logs.
