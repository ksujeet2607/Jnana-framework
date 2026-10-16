<?php

declare(strict_types=1);

namespace Jnana\Security;

use Core\ConfigManager;
use Jnana\Exceptions\ForbiddenException;
use Jnana\Exceptions\UnauthorizedException;

/** Centralized authorization based on the authenticated user, roles and permissions. */
final class AuthorizationService
{
    public function __construct(
        private AuthManager $auth,
        private ConfigManager $config
    ) {
    }

    public function can(string $permission): bool
    {
        $user = $this->auth->getUser();
        if ($user === null) {
            return false;
        }

        return in_array($permission, $this->effectivePermissions($user), true)
            || in_array('*', $this->effectivePermissions($user), true);
    }

    /** A user must have at least one required role and the configured permissions. */
    public function authorize(
        array $requiredRoles = [],
        array $requiredPermissions = [],
        bool $requireAllPermissions = true
    ): void {
        $user = $this->auth->getUser();
        if ($user === null) {
            throw new UnauthorizedException('Authentication is required to access this resource.');
        }

        $roles = $user['roles'] ?? [];
        $roles = is_array($roles) ? $roles : [];

        if ($requiredRoles !== [] && array_intersect($requiredRoles, $roles) === []) {
            throw new ForbiddenException('Your account does not have the required role to access this resource.');
        }

        if ($requiredPermissions === []) {
            return;
        }

        $effectivePermissions = $this->effectivePermissions($user);
        $hasWildcard = in_array('*', $effectivePermissions, true);
        $checks = array_map(
            static fn(string $permission): bool => $hasWildcard || in_array($permission, $effectivePermissions, true),
            $requiredPermissions
        );
        $allowed = $requireAllPermissions
            ? !in_array(false, $checks, true)
            : in_array(true, $checks, true);

        if (!$allowed) {
            throw new ForbiddenException('Your account does not have the required permission to perform this action.');
        }
    }

    /**
     * Effective permissions are the union of permissions explicitly attached to
     * the user session and permissions configured for each of the user's roles.
     *
     * @return list<string>
     */
    private function effectivePermissions(array $user): array
    {
        $permissions = $user['permissions'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }

        $roles = $user['roles'] ?? [];
        $roles = is_array($roles) ? $roles : [];
        $rolePermissions = $this->config->get('Authorization.role_permissions', []);
        if (is_array($rolePermissions)) {
            foreach ($roles as $role) {
                if (!is_string($role)) {
                    continue;
                }
                $mapped = $rolePermissions[$role] ?? [];
                if (is_array($mapped)) {
                    $permissions = array_merge($permissions, $mapped);
                }
            }
        }

        return array_values(array_unique(array_filter(
            $permissions,
            static fn(mixed $permission): bool => is_string($permission) && $permission !== ''
        )));
    }
}
