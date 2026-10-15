<?php

namespace Jnana\Facades;

use Jnana\Support\Facade;

/**
 * @mixin \Jnana\Http\Request
 */
class Request extends Facade
{
    protected static function accessor(): string
    {
        return \Jnana\Http\Request::class;
    }
}
