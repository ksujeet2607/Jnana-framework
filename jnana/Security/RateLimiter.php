<?php

declare(strict_types=1);

namespace Jnana\Security;

/**
 * Small file-backed fixed-window rate limiter.
 *
 * Intended for single-server deployments. Use a shared atomic store (for example
 * Redis) when the application runs on multiple servers/containers.
 */
final class RateLimiter
{
    private string $directory;

    public function __construct()
    {
        $configured = getenv('SECURITY_RATE_LIMIT_DIR');
        $this->directory = $configured ?: rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'jnana-security-rate-limits';
    }

    /**
     * Consume one attempt for a bucket. Returns true when allowed.
     */
    public function consume(string $key, int $limit, int $windowSeconds): bool
    {
        $limit = max(1, $limit);
        $windowSeconds = max(1, $windowSeconds);

        if (!is_dir($this->directory)
            && !@mkdir($this->directory, 0700, true)
            && !is_dir($this->directory)) {
            // Fail closed: inability to enforce the limit must not allow unlimited attempts.
            return false;
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        $handle = @fopen($path, 'c+');
        if ($handle === false) {
            return false;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return false;
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $state = is_string($raw) ? json_decode($raw, true) : null;
            $now = time();

            if (!is_array($state)
                || !isset($state['started_at'], $state['count'])
                || !is_numeric($state['started_at'])
                || !is_numeric($state['count'])
                || ($now - (int) $state['started_at']) >= $windowSeconds
                || $now < (int) $state['started_at']) {
                $state = ['started_at' => $now, 'count' => 0];
            }

            $allowed = (int) $state['count'] < $limit;
            if ($allowed) {
                $state['count'] = (int) $state['count'] + 1;
            }

            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_THROW_ON_ERROR));
            fflush($handle);
            flock($handle, LOCK_UN);

            return $allowed;
        } catch (\Throwable) {
            return false;
        } finally {
            fclose($handle);
        }
    }

    /** Seconds until the current fixed window ends, rounded up to at least one. */
    public function retryAfter(string $key, int $windowSeconds): int
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.json';
        $raw = @file_get_contents($path);
        $state = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($state) || !is_numeric($state['started_at'] ?? null)) {
            return max(1, $windowSeconds);
        }

        return max(1, ((int) $state['started_at'] + max(1, $windowSeconds)) - time());
    }
}
