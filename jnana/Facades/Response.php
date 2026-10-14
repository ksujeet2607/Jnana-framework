<?php

namespace Jnana\Facades;

use Jnana\Support\Facade;

/**
 * @mixin \Library\Http\Response
 */
class Response extends Facade
{
    protected static function accessor(): string
    {
        return \Library\Http\Response::class;
    }
}
