<?php

if (! function_exists('app_session_const')) {
    function app_session_const(string $key){
        if(isset($_SESSION['app_fixed_values'])){
            if(key_exists($key, $_SESSION['app_fixed_values'])){
                return $_SESSION['app_fixed_values'][strtolower($key)] ?? '';
            }
        }
        return false;
    }
}

if (!function_exists('with_old_input')) {
    function with_old_input(array $input): void
    {
        $_SESSION['_old_input'] = $input;
    }
}

if (!function_exists('all_old')) {
    function all_old(): array
    {
        return $_SESSION['_old_input'] ?? [];
    }
}

if (!function_exists('error')) {
    function error(string $key): string
    {
        return $_SESSION['_errors'][$key] ?? '';
    }
}

if (!function_exists('has_error')) {
    function has_error(string $key): bool
    {
        return isset($_SESSION['_errors'][$key]);
    }
}

//if (!function_exists('flash')) {
//    function flash(string $key, ?string $message = null)
//    {
//        if ($message !== null) {
//            $_SESSION['_flash'][$key] = $message;
//        } else {
//            $msg = $_SESSION['_flash'][$key] ?? '';
//            unset($_SESSION['_flash'][$key]);
//            return $msg;
//        }
//    }
//}

if (!function_exists('flash')) {
    function flash(): \Library\Session\SessionManager
    {
        return app(\Library\Session\SessionManager::class);
    }
}

if (!function_exists('clear_error')) {
    function clear_error(?string $key = ''): void
    {
        if(isset($_SESSION['_errors'][$key])){
            unset($_SESSION['_errors'][$key]);
            return;
        }
        unset($_SESSION['_errors']);
    }
}

if (!function_exists('clear_old_input')) {
    function clear_old_input(): void
    {
        unset($_SESSION['_old_input']);
    }
}