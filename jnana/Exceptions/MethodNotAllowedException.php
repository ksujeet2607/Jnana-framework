<?php

namespace Jnana\Exceptions;

class MethodNotAllowedException extends HttpException
{
    protected int $statusCode = 405;

    public function __construct(string $message = 'Method not allowed')
    {
        parent::__construct($message);
    }
}
