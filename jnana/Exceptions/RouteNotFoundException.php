<?php

namespace Jnana\Exceptions;

class RouteNotFoundException extends HttpException
{
    protected int $statusCode = 404;

    public function __construct(string $message = 'Route not found')
    {
        parent::__construct($message);
    }
}
