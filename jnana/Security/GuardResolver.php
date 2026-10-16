<?php

declare(strict_types=1);

namespace Jnana\Security;

use DI\Container;
use Jnana\Contracts\GuardInterface;
use Jnana\Http\Request;
use Jnana\Exceptions\ForbiddenException;
use Jnana\Exceptions\UnauthorizedException;
use Throwable;
use ReflectionClass;
use ReflectionMethod;

final class GuardResolver
{
    public function __construct(private Container $container)
    {
    }

    public function resolveAndRun(
        string $controller,
        string $method,
        Request $request
    ): void {
        $reflectionClass = new ReflectionClass($controller);
        $reflectionMethod = new ReflectionMethod($controller, $method);

        // Run explicit guards first. Authentication guards can establish the
        // expected identity before declarative authorization is evaluated.
        $guards = [
            ...$reflectionClass->getAttributes(Guard::class),
            ...$reflectionMethod->getAttributes(Guard::class),
        ];

        foreach ($guards as $attribute) {
            $metadata = $attribute->newInstance();
            $guard = $this->container->make($metadata->guardClass, $metadata->arguments);

            if (!$guard instanceof GuardInterface) {
                throw new \RuntimeException(
                    "{$metadata->guardClass} must implement GuardInterface"
                );
            }

            $guard->authorize($request);
        }

        $authorizeAttributes = [
            ...$reflectionClass->getAttributes(Authorize::class),
            ...$reflectionMethod->getAttributes(Authorize::class),
        ];
        $permissionAttributes = [
            ...$reflectionClass->getAttributes(Permission::class),
            ...$reflectionMethod->getAttributes(Permission::class),
        ];

        // Do not resolve authentication/database services for public routes that
        // have no declarative authorization metadata.
        if ($authorizeAttributes === [] && $permissionAttributes === []) {
            return;
        }

        /** @var AuthorizationService $authorization */
        $authorization = $this->container->get(AuthorizationService::class);

        foreach ($authorizeAttributes as $attribute) {
            $metadata = $attribute->newInstance();
            try {
                $authorization->authorize(
                    $metadata->roles,
                    $metadata->permissions,
                    $metadata->requireAllPermissions
                );
            } catch (UnauthorizedException|ForbiddenException $e) {
                $this->auditAuthorizationDenial($request, $controller, $method, $e);
                throw $e;
            }
        }

        foreach ($permissionAttributes as $attribute) {
            $metadata = $attribute->newInstance();
            try {
                $authorization->authorize(
                    [],
                    $metadata->names(),
                    $metadata->requireAll
                );
            } catch (UnauthorizedException|ForbiddenException $e) {
                $this->auditAuthorizationDenial($request, $controller, $method, $e);
                throw $e;
            }
        }
    }

    private function auditAuthorizationDenial(Request $request, string $controller, string $method, Throwable $exception): void
    {
        try {
            $audit = $this->container->get(SecurityAuditLogger::class);
            $audit->record('authorization.denied', [
                'controller' => $controller,
                'action' => $method,
                'reason' => $exception instanceof UnauthorizedException ? 'unauthenticated' : 'forbidden',
            ], $request);
        } catch (Throwable) {
            // Audit failures must not bypass authorization or hide the original denial.
        }
    }
}
