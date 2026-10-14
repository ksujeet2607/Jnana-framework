<?php

use Jnana\Http\Response;
use Jnana\Http\Request;

if (!function_exists('response')) {
    function response(): Response
    {
        return app(Response::class);
    }
}

if (!function_exists('request')) {
    function request(): Request
    {
        return app(Request::class);
    }
}

if (!function_exists('redirect')) {

    function redirect(string $url, array $messages = [], bool $status = false): Response
    {
        $r = response()->redirect($url);
        if ($status) {
            return $r->withSuccess($messages);
        }

        return $r->withError($messages);
    }
}
