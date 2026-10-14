<?php

namespace Jnana\Session;

class SessionManager
{

    const FLASH_KEY = '_flash_messages';
    const DB_NAME_KEY = 'defaultFy';
    const DB_NAME = 'currentDbName';

    /**
     * Start the session if not already started.
     */
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Set a session variable.
     *
     * @param string $key
     * @param mixed $value
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Get a session variable with an optional default value.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if a session variable exists.
     *
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session variable.
     *
     * @param string $key
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Destroy the session completely.
     */
    public function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }


    /**
     * Regenerate the session ID to prevent session fixation attacks.
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Set a flash message (available only for the next request).
     *
     * @param string $key
     * @param string $message
     */
    public function setFlash(string $key, string $message): void
    {
        $_SESSION[self::FLASH_KEY] ??= [];
        $_SESSION[self::FLASH_KEY][$key] = $message;
    }

    /**
     * Get and remove a flash message.
     *
     * @param string $key
     * @return string|null
     */
    public function getFlash(string $key): ?string
    {
        if (!isset($_SESSION[self::FLASH_KEY][$key])) {
            return null;
        }

        $message = $_SESSION[self::FLASH_KEY][$key];
        unset($_SESSION[self::FLASH_KEY][$key]);

        return $message;
    }


    /**
     * Get all flash messages and clear them.
     *
     * @return array
     */
    public function getAllFlashes(): array
    {
        $flashes = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);
        return $flashes;
    }

    public function success(string $message): void
    {
        $this->setFlash('success', $message);
    }

    public function error(string $message): void
    {
        $this->setFlash('error', $message);
    }


}
