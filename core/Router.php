<?php

namespace Core;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use JetBrains\PhpStorm\NoReturn;
use Library\Exceptions\HttpException;
use Library\Exceptions\MethodNotAllowedException;
use Library\Exceptions\RouteNotFoundException;
use Library\Middleware\Middleware;
use Library\Routes\Route;
use Library\Routes\RouteGroup;
use Psr\Log\LoggerInterface;
use function FastRoute\simpleDispatcher;

class Router
{
    private array $middlewares = [];
    private array $currentRoute = [];
    private array $controllerClasses = [];

    private Dispatcher $dispatcher;
    private Request $request;

    public function __construct(
        private LoggerInterface $logger,
        private ApplicationPaths $paths
    ) {}

    /**
     * Registers routes from a list of namespaces.
     */
    public function registerRoutesFromNamespace(array $namespaces): void
    {
        foreach ($namespaces as $namespace) {
            $directory = $this->paths->namespaceDirectory($namespace);
            $this->controllerClasses = array_merge(
                $this->controllerClasses,
                $this->getClassesInNamespace($namespace, $directory)
            );
        }

        $this->registerAttributeRoutes($this->controllerClasses);
    }

    /**
     * Retrieves all classes in a namespace recursively.
     */
    private function getClassesInNamespace(string $namespace, string $directory): array
    {
        $classes = [];

        if (!is_dir($directory)) {
            throw new \RuntimeException("Namespace directory not found: $namespace");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        $regex = new \RegexIterator($iterator, '/^.+\.php$/i', \RecursiveRegexIterator::GET_MATCH);

        foreach ($regex as $file => $_) {
            // Convert file path → FQDN
            $relativePath = str_replace([$directory, '/', '.php'], ['', '\\', ''], $file);
            $class = $namespace . $relativePath;

            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /**
     * Registers routes defined by attributes in controller classes.
     */
    private function registerAttributeRoutes(array $controllers): self
    {
        $this->dispatcher = simpleDispatcher(function (RouteCollector $r) use ($controllers) {
            foreach ($controllers as $controller) {
                $this->processControllerRoutes($r, $controller);
            }
        });

        return $this;
    }

    /**
     * Processes routes for a single controller class.
     */
    private function processControllerRoutes(RouteCollector $r, string $controller): void
    {
        try {
            $reflectionController = new \ReflectionClass($controller);

            $groupAttribute = $reflectionController->getAttributes(RouteGroup::class)[0] ?? null;
            $controllerMiddlewareAttr  = $reflectionController->getAttributes(Middleware::class)[0] ?? null;

            $groupConfig = $groupAttribute?->newInstance();

            $controllerMiddlewares = $controllerMiddlewareAttr?->newInstance()->middlewares ?? [];

            // Normalize group prefix
            $prefix = $groupConfig?->prefix ?? '';
            if ($prefix && !str_starts_with($prefix, '/')) {
                $prefix = '/' . ltrim($prefix, '/');
            }
            if ($prefix && str_ends_with($prefix, '/')) {
                $prefix = rtrim($prefix, '/');
            }

            // Add one group per controller
            $r->addGroup($prefix, function (RouteCollector $r) use ($controller, $controllerMiddlewares, $groupConfig, $reflectionController) {
                foreach ($reflectionController->getMethods() as $method) {
                    $attributes = $method->getAttributes(Route::class, \ReflectionAttribute::IS_INSTANCEOF);

                    $methodMiddlewareAttr =
                        $method->getAttributes(Middleware::class)[0] ?? null;

                    $methodMiddlewares =
                        $methodMiddlewareAttr?->newInstance()->middlewares ?? [];

                    foreach ($attributes as $attribute) {
                        $route = $attribute->newInstance();
                        $path = '';

                        if($route->routePath)
                            $path = str_starts_with($route->routePath, '/') ? $route->routePath : '/' . $route->routePath;

                        $r->addRoute($route->requestMethod, $path, [$controller, $method->getName()]);

                        // Store middleware info
                        $key = $controller . '::' . $method->getName();
                        $this->middlewares[$key] = array_values(array_merge(
                            $controllerMiddlewares,
                            $groupConfig?->middlewares ?? [],
                            $methodMiddlewares,
                            $route?->middlewares ?? []
                        ));

                        $this->validateMiddlewareDefinitions($this->middlewares[$key]);
                    }
                }
            });

        }catch (\Exception $exception){
            $this->logger->error($exception->getMessage());
        }

    }

    private function validateMiddlewareDefinitions(array $middlewares): void
    {
        foreach ($middlewares as $mw) {

            // Case 1: simple middleware
            if (is_string($mw)) {
                continue;
            }

            // Case 2: middleware with arguments
            if (
                is_array($mw) &&
                count($mw) === 2 &&
                is_string($mw[0]) &&
                is_array($mw[1])
            ) {
                continue;
            }

            throw new \InvalidArgumentException(
                'Invalid middleware definition. Use ClassName or [ClassName, arguments].'
            );
        }
    }


    /**
     * Normalize middleware by simple or argument
     * @param array $middlewares
     * @return array
     */
    private function normalizeMiddlewares(array $middlewares): array
    {
        $normalized = [];

        foreach ($middlewares as $middleware) {
            // Case 1: Simple middleware class
            if (is_string($middleware)) {
                $normalized[] = $middleware;
                continue;
            }

            // Case 2: Middleware with arguments
            if (is_array($middleware) && isset($middleware[0])) {
                $normalized[] = $middleware;
                continue;
            }

            throw new \RuntimeException('Invalid middleware definition');
        }

        return $normalized;
    }


    /**
     * Resolves the current request to a handler, parameters, and middleware stack.
     */
    public function resolve(string $httpMethod, string $uri): array
    {
        $uri = $this->sanitizeUri($uri);
        $routeInfo = $this->dispatcher->dispatch($httpMethod, $uri);

        return match ($routeInfo[0]) {
            Dispatcher::NOT_FOUND => throw new RouteNotFoundException(),
            Dispatcher::METHOD_NOT_ALLOWED => throw new MethodNotAllowedException(),
            Dispatcher::FOUND => $this->handleFound($routeInfo, $httpMethod),
            default => throw new \RuntimeException("Unexpected route dispatch result: {$routeInfo[0]}")
        };
    }

    /**
     * Handles route resolution for `FOUND`.
     */
    private function handleFound(array $routeInfo, string $httpMethod): array
    {
        $handler = $routeInfo[1];
        $parameters = $routeInfo[2];
        $key = is_array($handler) ? implode('::', $handler) : $handler;
        $middlewares = $this->middlewares[$key] ?? [];

        $routeGroup = '';
        if(class_exists($handler[0])) {
            $reflectionController = new \ReflectionClass($handler[0]);

            $groupAttribute = $reflectionController->getAttributes(RouteGroup::class)[0] ?? null;
            $groupConfig = $groupAttribute?->newInstance();

            $routeGroup = $groupConfig?->prefix ?? '';
        }

        $this->currentRoute = [
            'handler' => $handler,
            'path' => $routeInfo[2] ?? '',
            'method' => $httpMethod,
            'parameters' => $parameters,
            'middlewares' => $middlewares,
            'route_group' => $routeGroup,
        ];

        return [$handler, $parameters, $middlewares];
    }

    /**
     * Sanitizes the URI by removing query strings.
     */
    private function sanitizeUri(string $uri): string
    {
        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }
        return rawurldecode($uri);
    }

    public function getMiddlewares(): array
    {
        return $this->middlewares;
    }

    public function getCurrentRouteInfo(): array
    {
        return $this->currentRoute;
    }
}
