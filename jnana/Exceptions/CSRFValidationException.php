<?php

namespace Jnana\Exceptions;

class CSRFValidationException extends ForbiddenException
{
    public function __construct(string $message = 'Invalid request, CSRF validation failed')
    {
        parent::__construct($message);
    }
}
