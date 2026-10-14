<?php

namespace Jnana\Middleware;

use DI\Container;
use Jnana\Http\Request;
use Jnana\Http\Response;

final class MiddlewareDispatcher
{
    public function __construct(
        private Container $container
    ) {}

    public function dispatch(
        array $middlewares,
        Request $request,
        Response $response,
        callable $controller
    ): Response {
        $runner = array_reduce(
            array_reverse($middlewares),
            fn ($next, $middlewareDef) =>
            fn (Request $req, Response $res) =>
            $this->runMiddleware($middlewareDef, $req, $res, $next),
            fn (Request $req, Response $res) => $controller($req, $res)
        );

        $result =  $runner($request, $response);
        if (!$result instanceof Response) {
            throw new \LogicException(
                sprintf(
                    'Middleware/controller returned invalid response (%s). Ensure process() returns Response.',
                    get_debug_type($result)
                )
            );
        }

        return $result;
    }

    private function runMiddleware(
        mixed $middlewareDef,
        Request $request,
        Response $response,
        callable $next
    ): Response {
        // Case 1: Simple middleware class
        if (is_string($middlewareDef)) {
            $middleware = $this->container->get($middlewareDef);
            return $middleware->process($request, $response, $next);
        }

        // Case 2: Middleware with arguments
        if (is_array($middlewareDef)) {
            [$class, $args] = $middlewareDef;
            $middleware = $this->container->get($class);

            if (method_exists($middleware, 'withArguments')) {
                $middleware->withArguments($args);
            }

            return $middleware->process($request, $response, $next);
        }

        throw new \RuntimeException('Invalid middleware definition');
    }
}
