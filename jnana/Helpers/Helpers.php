<?php

declare(strict_types=1);

/**
 * Load all framework helper files (jnana/Helpers/*.php).
 *
 * This file used to also glob `<project-root>/app/Helpers` via a
 * `dirname(__DIR__, 2)` relative path — that assumed `jnana/` lived at
 * the project root next to `app/`. Once this package is installed under
 * `vendor/`, that relative path no longer points at the consuming
 * application, so it's been removed. If your app has its own
 * `app/Helpers/*.php` files, autoload them from your app's own
 * composer.json instead, e.g.:
 *
 *   "autoload": {
 *     "files": ["app/Helpers/load.php"]
 *   }
 *
 * where `app/Helpers/load.php` globs and requires that directory.
 */

foreach (glob(__DIR__ . '/*.php') as $file) {
    if ($file !== __FILE__) {
        require_once $file;
    }
}
