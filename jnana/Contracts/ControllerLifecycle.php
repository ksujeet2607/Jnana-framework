<?php

namespace Jnana\Contracts;

use Jnana\Http\Request;
use Jnana\Http\Response;

interface ControllerLifecycle
{
   public function beforeAction(Request $request): void;

   public function afterAction(Request $request, Response $response): Response;

}