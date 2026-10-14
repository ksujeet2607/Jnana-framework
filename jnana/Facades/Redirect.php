<?php

namespace Jnana\Facades;

use Jnana\Http\RedirectResponse;
use Jnana\Support\Facade;

/**
 * @mixin RedirectResponse
 */
class Redirect extends Facade
{

    /**
     * @inheritDoc
     */
    protected static function accessor(): string
    {
        return RedirectResponse::class;
    }
}