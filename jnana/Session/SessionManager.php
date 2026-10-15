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
    private const LAST_ACTIVITY_KEY = '_jnana_last_activity';
    private const SESSION_CREATED_KEY = '_jnana_session_created';

    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Strict mode rejects uninitialized session IDs supplied by clients.
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');

            $https = isset($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== ''
                && strtolower((string) $_SERVER['HTTPS']) !== 'off';
            $secure = filter_var(getenv('SESSION_COOKIE_SECURE') ?: ($https ? '1' : '0'), FILTER_VALIDATE_BOOLEAN);
            $httpOnly = filter_var(getenv('SESSION_COOKIE_HTTPONLY') ?: '1', FILTER_VALIDATE_BOOLEAN);
            $sameSite = getenv('SESSION_COOKIE_SAMESITE') ?: 'Lax';
            if (!in_array($sameSite, ['Strict', 'Lax', 'None'], true)) {
                $sameSite = 'Lax';
            }

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => getenv('SESSION_COOKIE_DOMAIN') ?: '',
                'secure' => $secure,
                'httponly' => $httpOnly,
                'samesite' => $sameSite,
            ]);
            session_start();
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $now = time();
            $idleTimeout = max(0, (int) (getenv('SESSION_IDLE_TIMEOUT') ?: 0));
            $absoluteTimeout = max(0, (int) (getenv('SESSION_ABSOLUTE_TIMEOUT') ?: 0));
            $lastActivity = (int) ($_SESSION[self::LAST_ACTIVITY_KEY] ?? $now);
            $createdAt = (int) ($_SESSION[self::SESSION_CREATED_KEY] ?? $now);

            if (($idleTimeout > 0 && ($now - $lastActivity) > $idleTimeout)
                || ($absoluteTimeout > 0 && ($now - $createdAt) > $absoluteTimeout)) {
                $this->destroy();
                session_start();
                $now = time();
            }

            $_SESSION[self::SESSION_CREATED_KEY] ??= $now;
            $_SESSION[self::LAST_ACTIVITY_KEY] = $now;
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
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?? '',
                'secure' => (bool) ($params['secure'] ?? false),
                'httponly' => (bool) ($params['httponly'] ?? true),
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }


    /**
     * Regenerate the session ID to prevent session fixation attacks.
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
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
