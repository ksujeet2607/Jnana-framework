<?php
if (!function_exists('csrf_token')) {
    function csrf_token(): void
    {
        if(class_exists(\Jnana\Security\SecurityService::class)){
            echo app(\Jnana\Security\SecurityService::class)->insertHiddenToken();
        }
    }
}
if (!function_exists('csrf_meta')) {
    /**
     * Render a CSRF meta tag for JavaScript clients.
     * Include this in the shared HTML head when using the Jnana security helper.
     */
    function csrf_meta(): void
    {
        if (class_exists(\Jnana\Security\SecurityService::class)) {
            $token = app(\Jnana\Security\SecurityService::class)->getCSRFToken();
            $lifetime = app(\Jnana\Security\SecurityService::class)->getTokenLifetime();
            echo '<meta name="csrf-token" content="'
                . htmlspecialchars($token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . '" data-lifetime="' . (int) $lifetime
                . '" data-issued-at="' . time() . '">';
        }
    }
}
