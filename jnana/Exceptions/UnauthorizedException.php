<?php

namespace Jnana\Exceptions;

class UnauthorizedException extends HttpException
{
    protected int $statusCode = 401;

    public function __construct(string $message = 'Authentication required')
    {
        parent::__construct($message);
    }
}