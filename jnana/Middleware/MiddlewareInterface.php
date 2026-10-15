<?php

namespace Jnana\Middleware;


use Jnana\Http\Request;
use Jnana\Http\Response;

interface MiddlewareInterface
{

    /**
     * @param Request $request
     * @param Response $response
     * @param callable $next
     * @return \Jnana\Http\Response
     */
    public function process(Request $request, Response $response, callable $next): Response;
}