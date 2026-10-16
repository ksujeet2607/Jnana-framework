<?php

namespace Core;

use DI\Container;
use Exception;
use FastRoute\BadRouteException;
use Jnana\Contracts\ControllerLifecycle;
use Jnana\Exceptions\HttpException;
use Jnana\Exceptions\CSRFTokenExpiredException;
use Jnana\Exceptions\CSRFValidationException;
use Jnana\Exceptions\UnauthorizedException;
use Jnana\Http\RedirectResponse;
use Jnana\Http\Request;
use Jnana\Http\Response;
use Jnana\Middleware\MiddlewareDispatcher;
use Jnana\Security\GuardResolver;
use Psr\Log\LoggerInterface;
use RuntimeException;


/**
 * Class AppManager
 * Manages the application lifecycle, including routing and middleware.
 */
final class AppManager
{
    private static ?AppManager $instance = null;
    private array $preControllerMiddlewares = [];
    private array $postControllerMiddlewares = [];
    public Router $router;

    /**
     * @throws Exception
     */
    public function __construct(
        private readonly Container       $container,
        private readonly ConfigManager   $config,
        private readonly LoggerInterface $logger
    ) {
        $this->router = new Router($this->logger, $container->get(ApplicationPaths::class));
        $container->set('router', $this->router);
        try {
            $this->loadGlobalMiddlewares();
            $this->validateRoutes();
            $this->registerRoutes();
            $this->container->set(
                MiddlewareDispatcher::class,
                new MiddlewareDispatcher($this->container)
            );
        } catch (Exception $e) {
            $this->logger->error('Initialization error', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Load pre and post controller middlewares from configuration.
     */
    private function loadGlobalMiddlewares(): void
    {
        $this->preControllerMiddlewares = $this->config->get('pre_controller_middlewares', []);
        $this->postControllerMiddlewares = $this->config->get('post_controller_middlewares', []);
    }

    public static function setInstance(AppManager $app): void
    {
        self::$instance = $app;
    }

    public static function instance(): AppManager
    {
        if (!self::$instance) {
            throw new RuntimeException("AppManager instance not set.");
        }
        return self::$instance;
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function getInstance($abstract)
    {
        return $this->container->get($abstract);
    }

    /**
     * Runs the application by handling the current request.
     */
    public function run(): void
    {
        // Create request and bind it to container
        $request = new Request();
        $this->container->set(Request::class, $request);
        $this->container->set('request', $request);

        // Delegate everything else to handleRequest()
        // All HTTP / Guard / Controller / Middleware exceptions
        // are handled there
        $this->handleRequest(
            $request->getMethod(),
            $request->getUri()
        );
    }



    /**
     * Validates the routes' configuration.
     */
    private function validateRoutes(): void
    {
        if (empty($this->config->getRoutes()) || !is_array($this->config->getRoutes())) {
            throw new BadRouteException("Routes must be a non-empty array.");
        }
    }

    /**
     * Registers routes from the configuration.
     */
    private function registerRoutes(): void
    {
        $this->router->registerRoutesFromNamespace($this->config->getRoutes());
    }

    /**
     * Handles the incoming HTTP request.
     *
     * @throws Exception Only for unrecoverable system errors
     */
    /**
     * Handles the incoming HTTP request.
     * Execution order
     * 1. Global PRE middleware
     * 2. Route resolution
     * 3. Controller instantiation
     * 4. beforeAction()
     * 5. Route middleware
     * 6. Guards
     * 7. Controller action
     * 8. afterAction()
     * 9. Global POST middleware
     * 10.Send response
    */
    private function handleRequest(string $method, string $uri): void
    {

        $request  = $this->container->get(Request::class);
        $response = new Response();

        $response->addHeader('X-Requested-Url', $uri);

        $this->logger->info('Request started', [
            'method' => $method,
            'uri'    => $uri,
        ]);

        try {
            $dispatcher = $this->container->get(MiddlewareDispatcher::class);
            /* ---------------------------------------------------------
             * 1. Global PRE-controller middleware
             * ------------------------------------------------------- */
            $this->logger->info('Executing global PRE middlewares');

            $response = $dispatcher->dispatch(
                $this->preControllerMiddlewares,
                $request,
                $response,
                fn(Request $req, Response $res) => $res
            );

            if (!$response instanceof Response) {
                throw new RuntimeException('PRE middleware must return Response');
            }

            /* ---------------------------------------------------------
             * 2. Route resolution
             * ------------------------------------------------------- */
            $this->logger->info('Resolving route');


            [$handler, $params, $routeMiddlewares] = $this->router->resolve($method, $uri);

            $this->logger->info('Route resolved', [
                'handler'     => is_array($handler)
                    ? implode('::', $handler)
                    : (string)$handler,
                'params'      => $params,
                'middlewares' => $routeMiddlewares,
            ]);

            [$controllerClass, $action] = $handler;

            $controller = $this->container->get($controllerClass);

            /* ---------------------------------------------------------
             * 3. Route middleware + Guards + Controller execution
             * Authorization must complete before controller lifecycle hooks run.
             * ------------------------------------------------------- */
            $this->logger->info('Executing route middlewares, guards and controller');

            $guardCallable = function (Request $req, Response $res) use ($controllerClass, $action) {
                $this->container
                    ->get(GuardResolver::class)
                    ->resolveAndRun($controllerClass, $action, $req);

                return $res;
            };

            $controllerCallable = function (Request $req, Response $res) use ($controller, $action, $params) {
                if ($controller instanceof ControllerLifecycle) {
                    $controller->beforeAction($req);
                }

                return $controller->{$action}(...array_values($params));
            };

            $controllerResponse = $dispatcher->dispatch(
                $routeMiddlewares,
                $request,
                $response,
                function (Request $req, Response $res) use ($guardCallable, $controllerCallable) {
                    // 1. Guards
                    $guardCallable($req, $res);

                    // 2. Controller
                    return $controllerCallable($req, $res);
                }
            );

            if (!$controllerResponse instanceof Response) {
                $this->logger->warning(
                    'Controller returned non-Response; coercing',
                    ['returned' => get_debug_type($controllerResponse)]
                );
                $controllerResponse = (new Response())
                    ->setBody($controllerResponse);
            }

            /* ---------------------------------------------------------
             * Controller AFTER hook
             * ------------------------------------------------------- */
            if ($controller instanceof ControllerLifecycle) {
                $controllerResponse = $controller->afterAction(
                    $request,
                    $controllerResponse
                );
            }

            $response = $controllerResponse;

            /* ---------------------------------------------------------
             * 4. Global POST-controller middleware
             * ------------------------------------------------------- */
            $this->logger->info('Executing global POST middlewares');

            $response = $dispatcher->dispatch(
                $this->postControllerMiddlewares,
                $request,
                $response,
                fn(Request $req, Response $res) => $res
            );


            if (!$response instanceof Response) {
                throw new RuntimeException('POST middleware must return Response');
            }

            /* ---------------------------------------------------------
             * 5. Send response (single exit point)
             * ------------------------------------------------------- */
            $this->logger->info('Sending response', [
                'status' => $response->getStatusCode(),
            ]);

            $response->send();
        }
        catch (CSRFTokenExpiredException $e) {
            $this->logger->notice('CSRF token expired', ['method' => $method, 'uri' => $uri]);

            $expiredResponse = (new Response())
                ->setStatusCode(419)
                ->addHeader('Cache-Control', 'no-store, private');

            if ($request->isApiRequest() || $request->isAjax() || $request->expectsJson()) {
                $expiredResponse
                    ->addHeader('Content-Type', 'application/json')
                    ->setBody([
                        'error' => 'csrf_token_expired',
                        'message' => 'Your form session has expired. The token can be refreshed; review and submit again.',
                        'refresh_url' => '/security/csrf-token',
                    ]);
            } else {
                $expiredResponse
                    ->addHeader('Content-Type', 'text/html; charset=UTF-8')
                    ->setBody(<<<'HTML'
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Form session expired</title></head>
<body>
<main>
<h1>Form session expired</h1>
<p>Your form was not processed because its security token expired. Refreshing the token and returning to your form. Please review the values and submit again.</p>
<p><a href="javascript:history.back()">Return to the form</a></p>
</main>
<script>
(function () {
    fetch('/security/csrf-token', {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: {'Accept': 'application/json'}
    }).then(function (response) {
        if (!response.ok) throw new Error('Token refresh failed');
        return response.json();
    }).then(function () {
        if (history.length > 1) history.back();
    }).catch(function () {
        document.querySelector('main').insertAdjacentHTML('beforeend',
            '<p>Automatic recovery failed. Please sign in again or reload the form.</p>');
    });
})();
</script>
</body>
</html>
HTML);
            }

            $expiredResponse->send();
            return;
        }
        catch (CSRFValidationException $e) {
            $this->logger->warning('CSRF validation failed', ['method' => $method, 'uri' => $uri]);

            (new Response())
                ->setStatusCode(403)
                ->addHeader('Cache-Control', 'no-store, private')
                ->addHeader('Content-Type', 'application/json')
                ->setBody([
                    'error' => 'csrf_validation_failed',
                    'message' => 'The request could not be verified. Reload the page and try again.',
                ])
                ->send();
            return;
        }
        catch (HttpException $e) {

            $this->logger->warning('HTTP exception', [
                'status' => $e->getStatusCode(),
                'message' => $e->getMessage(),
            ]);

            if ($request->isApiRequest()) {
                (new Response())
                    ->setStatusCode($e->getStatusCode())
                    ->setBody([
                        'error' => $e->getMessage()
                    ])
                    ->send();
                return;
            }

            if ($e instanceof UnauthorizedException) {
                (new RedirectResponse(getenv('DEFAULT_DOMAIN')))->send();
                return;
            }

            // 403, 404, others
            (new Response())
                ->setStatusCode($e->getStatusCode())
                ->setBody(
                    ErrorRenderer::render($e->getStatusCode(), $e->getMessage(), $e)
                )
                ->send();
            return;
        }
        catch (Exception $e) {

            $this->logger->error('Unhandled exception', [
                'method' => $method,
                'uri' => $uri,
                'exception' => $e,
            ]);

            if ($request->isApiRequest()) {
                (new Response())
                    ->setStatusCode(500)
                    ->setBody(['error' => 'Internal server error'])
                    ->send();
                return;
            }

            throw $e; // Let Tracy / debugger handle it
        }

    }


}
