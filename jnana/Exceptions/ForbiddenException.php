<?php

namespace Jnana\Exceptions;

class ForbiddenException extends HttpException
{
    protected int $statusCode = 403;

    public function __construct(string $message = 'Access denied')
    {
        parent::__construct($message);
    }
}
