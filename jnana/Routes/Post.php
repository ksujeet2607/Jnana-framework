<?php

namespace Jnana\Routes;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Post extends Route
{
    public function __construct(string $routePath, array $middlewares = [])
    {
        parent ::__construct('POST', $routePath, $middlewares);
    }

}