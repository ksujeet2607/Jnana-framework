# Authorization and route permissions

Jnana supports centralized authorization through `Authorize` and `Permission` PHP attributes. The `GuardResolver`, invoked by `AppManager`, evaluates these attributes after route middleware and before controller lifecycle hooks or actions.

## Protect a controller or action

```php
use Jnana\Routes\Get;
use Jnana\Security\Authorize;
use Jnana\Security\Permission;

#[Authorize(roles: ['admin', 'manager'])]
final class UserController
{
    #[Get('/users')]
    #[Permission('users.view')]
    public function index()
    {
        // The controller is reached only after authorization succeeds.
    }

    #[Get('/users/create')]
    #[Authorize(permissions: ['users.create'])]
    public function create()
    {
        // ...
    }
}
```

Class and method attributes are additive: every declared requirement must pass. Within `roles`, the user must have at least one listed role. Within `permissions`, all permissions are required by default; set `requireAllPermissions: false` on `Authorize` or `requireAll: false` on `Permission` to accept any listed permission.

## Role-to-permission map

Configure `config/Authorization.php` in the application:

```php
return [
    'role_permissions' => [
        'admin' => ['users.view', 'users.create', 'users.update'],
        'manager' => ['reports.view'],
    ],
];
```

An authenticated user receives effective permissions from the union of their session `permissions` and the configured permissions for each session role. An absent permission mapping denies access. A `*` grant is supported but should be reserved for a deliberately privileged role.

## Manual checks

Use `app(\\Jnana\\Security\\AuthorizationService::class)->can('users.view')` for application policy checks. `auth()->can('users.view')` checks the same direct and configured role grants. Both return `false` when the user is unauthenticated or no grant exists.

## HTTP behavior

- Unauthenticated requests throw `UnauthorizedException` (HTTP 401).
- Authenticated users missing a role or permission receive `ForbiddenException` (HTTP 403).
- Authorization runs before `ControllerLifecycle::beforeAction()` and before the controller action.

## Important resource-level checks

Route permissions are not a replacement for record ownership and tenant isolation. For a route such as `/invoices/{id}`, load the record under the authenticated user's allowed company/tenant scope, or explicitly authorize the loaded record. Never trust a submitted record ID merely because the user can access the general route.
