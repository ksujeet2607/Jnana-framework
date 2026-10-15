<?php

namespace Jnana\Facades;

use Jnana\Support\Facade;

/**
 * @mixin \Jnana\Utilities\View
 */
class View extends Facade
{

    /**
     * @inheritDoc
     */
    protected static function accessor(): string
    {
        return \Jnana\Utilities\View::class;
    }
}