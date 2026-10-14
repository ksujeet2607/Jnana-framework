<?php

namespace Jnana\Exceptions;

class CompanyContextRequiredException extends ForbiddenException
{
    protected int $statusCode = 400;
    public function __construct(
        string $message = 'No company selected. Please select a company to continue.',
    ) {
        parent::__construct($message);
    }
}
