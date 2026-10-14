<?php

use Jnana\Security\AuthManager;

if (!function_exists('auth')) {
    function auth(): AuthManager
    {
        static $auth;

        if (null === $auth) {
            $auth = container()->get(AuthManager::class);
        }

        return $auth;
    }
}
