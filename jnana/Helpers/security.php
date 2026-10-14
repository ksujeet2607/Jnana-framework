<?php
if (!function_exists('csrf_token')) {
    function csrf_token(): void
    {
        if(class_exists(\Library\Security\SecurityService::class)){
            echo app(\Library\Security\SecurityService::class)->insertHiddenToken();
        }
    }
}