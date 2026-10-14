<?php

use Jnana\Database\Database;
use Jnana\Database\DatabaseInterface;

if (! function_exists('db')) {
    function db(): Database
    {
        static $db;

        if (null === $db) {
            $db = app(DatabaseInterface::class);
        }

        return $db;
    }
}
