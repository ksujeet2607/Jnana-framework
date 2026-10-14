<?php

namespace Jnana\Security;

use DI\Attribute\Inject;
use Jnana\Session\SessionManager;

class Authorization
{
    #[Inject]
    private SessionManager $sessionManager;

    public function isAuthorized(array $requiredRoles): bool
    {
        $user = $this->sessionManager->get('user');
        $userRoles = $user['roles'] ?? [];
        return !empty(array_intersect($requiredRoles, $userRoles));
    }
}