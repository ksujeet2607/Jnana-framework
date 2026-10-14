<?php

if (!function_exists('app')) {
    /**
     * @template T
     * @param class-string<T>|null $abstract
     * @return T|Core\AppManager
     */
    function app(?string $abstract = null)
    {
        static $app;

        if ($app === null) {
            $app = Core\AppManager::instance();
        }

        if ($abstract !== null) {
            return $app->container()->get($abstract);
        }

        return $app;
    }
}

if (! function_exists('container')) {
    function container(): \Psr\Container\ContainerInterface
    {
        static $container;

        if ($container === null) {
            $container = Core\AppManager::instance()->container();
        }

        return $container;
    }
}

if (! function_exists('app_const')) {
    function app_const(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        if(array_key_exists($key, $cache)){
            return $cache[$key];
        }

        $value = getenv($key);

        if ($value === false) {
           return $cache[$key] = $default;
        }

        return  $cache[$key] = $value;
    }
}