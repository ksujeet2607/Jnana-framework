<?php

namespace Jnana\Middleware;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Middleware
{
    public array $middlewares = [];
    public function __construct(array|callable|string $middleware, ?array $args = null)
    {
        $this->middlewares = $args === null
            ? (array) $middleware
            : [[ $middleware, $args ]];
    }

}