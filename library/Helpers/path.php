<?php

use Core\ApplicationPaths;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return ApplicationPaths::instance()->app($path);
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return ApplicationPaths::instance()->app('app' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : ''));
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return ApplicationPaths::instance()->config($path);
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return ApplicationPaths::instance()->storage($path);
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return ApplicationPaths::instance()->public($path);
    }
}

if (!function_exists('framework_path')) {
    function framework_path(string $path = ''): string
    {
        return ApplicationPaths::instance()->framework($path);
    }
}

if (!function_exists('vendor_path')) {
    function vendor_path(string $path = ''): string
    {
        return ApplicationPaths::instance()->vendor($path);
    }
}

if (!function_exists('view_path')) {
    function view_path(string $path = ''): string
    {
        return public_path('views' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('cache_path')) {
    function cache_path(string $path = ''): string
    {
        return storage_path('framework/temp/cache' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}
