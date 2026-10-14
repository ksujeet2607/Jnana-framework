<?php

namespace Jnana\Routes;

#[\Attribute(\Attribute::TARGET_METHOD|\Attribute::IS_REPEATABLE)]
class Get extends Route
{
    public function __construct(string $routePath, array $middlewares = [])
    {
        parent ::__construct('GET', $routePath, $middlewares);
    }

}