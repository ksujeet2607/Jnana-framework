<?php

namespace Jnana\Security;

use Core\ConfigManager;
use Jnana\Session\SessionManager;
use function DI\value;

class AuthManager
{
    private AuthStrategyInterface $strategy;
    private SessionManager $sessionManager;
    private ?string $lastError = null;
    private ConfigManager $configManager;

    // Error code → message mapping
    private array $errorMessages = [
        'user_not_found'       => 'User account not found.',
        'account_inactive'     => 'Your account is inactive. Please contact admin.',
        'invalid_credentials'  => 'Incorrect username or password.',
        'authentication_failed'=> 'Login failed. Please try again.',
        'session_expired'      => 'Your session has expired. Please log in again.',
    ];

    public function __construct(
        AuthStrategyInterface $strategy,
        SessionManager $sessionManager,
        ConfigManager $configManager,
        private ?SecurityAuditLogger $auditLogger = null
    ) {
        $this->strategy = $strategy;
        $this->sessionManager = $sessionManager;
        $this->configManager = $configManager;

        // Restore user from session if available
        if ($sessionManager->has('user')) {
            $this->setUser($sessionManager->get('user'));
        }
    }

    public function authenticate(string $username, string $password): bool
    {
        $authenticated = $this->strategy->authenticate($username, $password);

        if ($authenticated && $this->getUser()) {
            // Rotate the session identifier after successful authentication.
            $this->sessionManager->regenerate();
            $this->sessionManager->set('user', $this->getUser());
            $this->lastError = null;
            $this->audit('authentication.succeeded', [
                'user_id' => $this->getUser()['id'] ?? null,
            ]);
        } else {
            // Capture error from strategy
            $code = method_exists($this->strategy, 'getLastError')
                ? $this->strategy->getLastError()
                : 'authentication_failed';

            $this->lastError = $this->errorMessages[$code] ?? $this->errorMessages['authentication_failed'];
            $this->audit('authentication.failed', [
                'reason' => is_string($code) ? $code : 'authentication_failed',
            ]);
        }

        return $authenticated;
    }

    private function audit(string $event, array $context): void
    {
        try {
            $this->auditLogger?->record($event, $context);
        } catch (\Throwable) {
            // Audit logging must not break authentication or logout flows.
        }
    }

    public function getUser(): ?array
    {
        return $this->strategy->getUser();
    }

    public function getUserId(): ?int
    {
        return $this->getUser()['id'] ?? null;
    }

    public function setUser(?array $user): void
    {
        $this->strategy->setCurrentUser($user);

        if ($user === null) {
            $this->sessionManager->remove('user');
        } else {
            $this->sessionManager->set('user', $user);
        }
    }

    public function isAuthenticated(): bool
    {
        return $this->getUser() !== null;
    }

    public function hasRole(string $role): bool
    {
        $user = $this->getUser();
        if (!$user || !isset($user['roles']) || !is_array($user['roles'])) {
            return false;
        }
        return in_array($role, $user['roles'], true);
    }

    public function logout(): void
    {
        $this->audit('authentication.logout', [
            'user_id' => $this->getUser()['id'] ?? null,
        ]);
        $this->strategy->logout();
        $this->sessionManager->remove('user');
        // Rotate the identifier so the pre-logout session cannot be reused.
        $this->sessionManager->regenerate();
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function check(): bool
    {
        return $this->isAuthenticated();
    }

    public function user(): ?array
    {
        return $this->getUser();
    }

    public function id(): ?int
    {
        return $this->getUserId();
    }

    public function can(string $permission): bool
    {
        $user = $this->getUser();

        if (! $user) {
            return false;
        }

        $permissions = $user['permissions'] ?? [];
        if (!is_array($permissions)) {
            return false;
        }

        if (in_array($permission, $permissions, true) || in_array('*', $permissions, true)) {
            return true;
        }

        // Keep auth()->can() consistent with route authorization when an app
        // defines role-to-permission mappings. Missing mappings deny by default.
        $rolePermissions = $this->configManager?->get('Authorization.role_permissions', []) ?? [];
        $roles = $user['roles'] ?? [];
        if (!is_array($rolePermissions) || !is_array($roles)) {
            return false;
        }

        foreach ($roles as $role) {
            if (!is_string($role)) {
                continue;
            }
            $mapped = $rolePermissions[$role] ?? [];
            if (is_array($mapped) && (in_array($permission, $mapped, true) || in_array('*', $mapped, true))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     * Is user has admin scope
     */
    public function isAdminScope(): bool
    {
        return ($this->getUser()['scope'] ?? null) === 'admin';
    }

    /**
     * @return bool
     * Is user has company scope
     */
    public function isCompanyScope(): bool
    {
        return ($this->getUser()['scope'] ?? null) === 'company';
    }

    /**
     * Check whether the current user's auth_level matches the given
     * access-level enum case. Accepts any backed enum — your app defines
     * its own access-level enum (e.g. `enum AppAccessEnum: string { case ADMIN
     * = 'admin'; case COMPANY = 'company'; }`) and passes a case of it here.
     *
     * @param \BackedEnum $accessLevel
     */
    public function userAuthLevel(\BackedEnum $accessLevel): bool
    {
        return ($this->getUser()['auth_level'] ?? null) === $accessLevel->value;
    }

    public function getUserCompanyId(): ?int
    {
        $companyId = $this->user()['company_id'] ?? null;
        return is_numeric($companyId) ? (int) $companyId : null;
    }

}
