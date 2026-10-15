<?php
if (!function_exists('csrf_token')) {
    function csrf_token(): void
    {
        if(class_exists(\Jnana\Security\SecurityService::class)){
            echo app(\Jnana\Security\SecurityService::class)->insertHiddenToken();
        }
    }
}