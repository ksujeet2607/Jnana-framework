<?php

namespace Jnana\Middleware;

use Core\MaintenanceManager;
use Jnana\Exceptions\MaintenanceException;
use Jnana\Http\Request;
use Jnana\Http\Response;

class MaintenanceMiddleware implements MiddlewareInterface
{
    public function __construct(
        private MaintenanceManager $maintenance
    ) {}

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return Response
     */
    public function process(Request $request, Response $response, callable $next): Response
    {

        if (!$this->maintenance->isEnabled()) {
            return $next($request, $response); // continue request lifecycle
        }

        $clientIp = $request->getClientIp();
        $uri      = $request->getUri();

        if (
            $this->maintenance->isRouteBypassed($uri) ||
            $this->maintenance->isIpAllowed($clientIp)
        ) {
            return $next($request, $response);;
        }

        throw new MaintenanceException();

    }
}