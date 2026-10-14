<?php

namespace Jnana\Routes;

#[\Attribute(\Attribute::TARGET_METHOD|\Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(public string $requestMethod, public string $routePath, public array $middlewares = [])
    {
    }

}