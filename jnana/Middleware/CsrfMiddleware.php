<?php

declare(strict_types=1);

namespace Jnana\Middleware;

use Jnana\Exceptions\CSRFTokenExpiredException;
use Jnana\Exceptions\CSRFValidationException;
use Jnana\Http\Request;
use Jnana\Http\Response;
use Jnana\Security\SecurityService;

class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private SecurityService $securityService)
    {
    }

    public function process(Request $request, Response $response, callable $next): Response
    {
        $method = strtoupper($request->getMethod());
        $uri = $request->getUri();

        if (in_array($method, $this->securityService->getExcludedMethods(), true)
            || in_array($uri, $this->securityService->getExcludedRoutes(), true)) {
            return $next($request, $response);
        }

        if ($this->securityService->isTokenExpired($request)) {
            throw new CSRFTokenExpiredException();
        }

        if (!$this->securityService->validate($method, $uri, $request)) {
            throw new CSRFValidationException();
        }

        return $next($request, $response);
    }
}
