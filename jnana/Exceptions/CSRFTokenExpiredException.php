<?php

declare(strict_types=1);

namespace Jnana\Exceptions;

class CSRFTokenExpiredException extends HttpException
{
    protected int $statusCode = 419;

    public function __construct(string $message = 'CSRF token expired. Refresh the token and submit the form again.')
    {
        parent::__construct($message);
    }
}
