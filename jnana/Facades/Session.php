<?php

namespace Jnana\Facades;

use Jnana\Session\SessionManager;
use Jnana\Support\Facade;

/**
 * @mixin SessionManager
 */
class Session extends Facade
{
    protected static function accessor(): string
    {
        return SessionManager::class;
    }
}
