<?php

namespace Jnana\Support;

use Core\AppManager;

abstract class Facade
{
    /**
     * Return the container binding name
     */
    abstract protected static function accessor(): string;

    /**
     * Resolve the underlying instance
     */
    protected static function resolveInstance()
    {
        return AppManager::instance()
            ->container()
            ->get(static::accessor());
    }

    /**
     * Forward static calls to the instance
     */
    public static function __callStatic(string $method, array $arguments)
    {
        $instance = static::resolveInstance();

        if (!method_exists($instance, $method)) {
            throw new \BadMethodCallException(
                sprintf(
                    'Method %s::%s does not exist.',
                    static::accessor(),
                    $method
                )
            );
        }

        return $instance->$method(...$arguments);
    }
}
