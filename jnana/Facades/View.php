<?php

namespace Jnana\Facades;

use Jnana\Support\Facade;

/**
 * @mixin \Library\Utilities\View
 */
class View extends Facade
{

    /**
     * @inheritDoc
     */
    protected static function accessor(): string
    {
        return \Library\Utilities\View::class;
    }
}