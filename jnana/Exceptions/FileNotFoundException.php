<?php

namespace Jnana\Exceptions;

class FileNotFoundException extends HttpException
{
    protected int $statusCode = 404;

    public function __construct(string $message = 'File not found')
    {
        parent::__construct($message);
    }
}
