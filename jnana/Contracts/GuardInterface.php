<?php

namespace Jnana\Contracts;

use Jnana\Http\Request;

interface GuardInterface
{
    /**
     * Throw exception or return false to deny access
     */
    public function authorize(Request $request): void;
}