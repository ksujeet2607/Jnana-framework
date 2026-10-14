<?php

namespace Jnana\Security;

use DI\Container;
use Jnana\Contracts\GuardInterface;
use Jnana\Http\Request;
use ReflectionClass;
use ReflectionMethod;

class GuardResolver
{
    public function __construct(
        private Container $container
    ) {}

    public function resolveAndRun(
        string $controller,
        string $method,
        Request $request
    ): void {
        $guards = [];

        $rc = new ReflectionClass($controller);
        $rm = new ReflectionMethod($controller, $method);

        foreach ($rc->getAttributes(Guard::class) as $attr) {
            $guards[] = $attr->newInstance();
        }

        foreach ($rm->getAttributes(Guard::class) as $attr) {
            $guards[] = $attr->newInstance();
        }

        foreach ($guards as $guardMeta) {
            $guard = $this->container->make(
                $guardMeta->guardClass,
                $guardMeta->arguments
            );

            if (!$guard instanceof GuardInterface) {
                throw new \RuntimeException(
                    "{$guardMeta->guardClass} must implement GuardInterface"
                );
            }

            $guard->authorize($request);
        }
    }
}
