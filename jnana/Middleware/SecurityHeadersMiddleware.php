<?php

declare(strict_types=1);

namespace Jnana\Middleware;

use Core\ConfigManager;
use Jnana\Http\Request;
use Jnana\Http\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(private ConfigManager $config)
    {
    }

    public function process(Request $request, Response $response, callable $next): Response
    {
        $result = $next($request, $response);
        $settings = $this->config->get('Security', []);
        $headers = $settings['headers'] ?? [];

        $defaults = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        foreach (array_merge($defaults, $headers) as $name => $value) {
            if (is_string($name) && is_string($value) && $value !== '') {
                $result->addHeader($name, $value);
            }
        }

        if (filter_var($settings['hsts'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')) {
            $result->addHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $result;
    }
}
