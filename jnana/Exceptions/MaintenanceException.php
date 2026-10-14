<?php

namespace Jnana\Exceptions;

class MaintenanceException extends HttpException
{
    protected int $statusCode = 503;
    public function __construct(string $message = 'We’ll be back shortly.')
    {
        parent::__construct($message);
    }
}
