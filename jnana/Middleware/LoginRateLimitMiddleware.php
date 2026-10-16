<?php

declare(strict_types=1);

namespace Jnana\Middleware;

use Core\ConfigManager;
use Jnana\Http\Request;
use Jnana\Http\Response;
use Jnana\Security\RateLimiter;

/** Apply to login POST routes only; do not register globally. */
final class LoginRateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private RateLimiter $rateLimiter,
        private ConfigManager $config
    ) {
    }

    public function process(Request $request, Response $response, callable $next): Response
    {
        $settings = $this->config->get('Security.rate_limit.login', []);
        if (!is_array($settings) || !($settings['enabled'] ?? true)) {
            return $next($request, $response);
        }

        $limit = max(1, (int) ($settings['max_attempts'] ?? 5));
        $window = max(1, (int) ($settings['window_seconds'] ?? 900));
        $server = $request->getServer();
        // Deliberately use REMOTE_ADDR. Forwarded headers are attacker-controlled unless
        // a trusted-proxy layer explicitly normalizes them first.
        $ip = (string) ($server['REMOTE_ADDR'] ?? 'unknown');
        $identifierField = (string) ($settings['identifier_field'] ?? 'username');
        $identifier = $request->getPostParam($identifierField, '');
        $identifier = is_scalar($identifier) ? strtolower(trim((string) $identifier)) : '';

        $ipKey = 'login:ip:' . $ip;
        $accountKey = $identifier !== ''
            ? 'login:ip-account:' . hash('sha256', $ip . "\0" . $identifier)
            : null;

        $ipAllowed = $this->rateLimiter->consume($ipKey, $limit, $window);
        $accountAllowed = $accountKey === null
            ? true
            : $this->rateLimiter->consume($accountKey, $limit, $window);

        if (!$ipAllowed || !$accountAllowed) {
            $retryAfter = $this->rateLimiter->retryAfter(
                !$ipAllowed ? $ipKey : (string) $accountKey,
                $window
            );

            return $response
                ->setStatusCode(429)
                ->addHeader('Content-Type', 'application/json; charset=utf-8')
                ->addHeader('Cache-Control', 'no-store')
                ->addHeader('Retry-After', (string) $retryAfter)
                ->setBody([
                    'error' => 'rate_limit_exceeded',
                    'message' => 'Too many login attempts. Please wait before trying again.',
                    'retry_after' => $retryAfter,
                ]);
        }

        return $next($request, $response);
    }
}
