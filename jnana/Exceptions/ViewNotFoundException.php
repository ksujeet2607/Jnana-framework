<?php

namespace Jnana\Exceptions;

class ViewNotFoundException extends FileNotFoundException
{
    protected int $statusCode = 404;

    public function __construct(string $message = 'View not found')
    {
        parent::__construct($message);
    }
}
