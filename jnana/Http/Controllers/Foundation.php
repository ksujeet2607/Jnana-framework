<?php
namespace Jnana\Http\Controllers;

use Core\Loader;
use DI\Attribute\Inject;
use Doctrine\ORM\EntityManagerInterface;
use Jnana\Contracts\Authenticatable;
use Jnana\Contracts\AppContextInterface;
use Jnana\Contracts\ControllerLifecycle;
use Jnana\Database\DatabaseInterface;
use Jnana\Facades\View;
use Jnana\Http\RedirectResponse;
use Jnana\Http\Request;
use Jnana\Http\Response;
use Jnana\Session\SessionManager;
use Jnana\Utilities\Utility;
use Psr\Log\LoggerInterface;

abstract class Foundation implements ControllerLifecycle
{
    use Utility;

    /* -------------------------------------------------
     | HTTP / Framework
     * -------------------------------------------------*/
    #[Inject] protected Request $request;
    #[Inject] protected Response $response;
    #[Inject] protected SessionManager $sessionManager;
    #[Inject] protected LoggerInterface $logger;

    /* -------------------------------------------------
     | Persistence
     * -------------------------------------------------*/
    #[Inject] protected DatabaseInterface $database;
    #[Inject] protected ?EntityManagerInterface $em = null;
    protected ?EntityManagerInterface $companyEm = null;

    /* -------------------------------------------------
     | Context / Domain
     * -------------------------------------------------*/
    #[Inject] protected AppContextInterface $appContext;

    /* -------------------------------------------------
     | Utilities
     * -------------------------------------------------*/
    #[Inject] protected ?Loader $loader = null;

    final public function beforeAction(Request $request): void
    {
        $this->onBeforeAction($request);
    }

    protected function onBeforeAction(Request $request): void
    {
        // child override point
    }

    final public function afterAction(Request $request, Response $response): Response
    {
        return $this->onAfterAction($request, $response);
    }

    protected function onAfterAction(Request $request, Response $response): Response
    {
        return $response;
    }

    protected function em()
    {
        return $this->em;
    }

    protected function getCurrentUser(): ?Authenticatable
    {
        return $this->appContext->user();
    }

    /**
     * @param string $template
     * @param array|null $data
     * @return Response
     */
    public function render(string $template, array $data = []): Response
    {
        $route = $this->request->getAttributes();

        $data['called_class'] = isset($route['handler'][0])
            ? $this->toResourceName($route['handler'][0])
            : null;

        $data['called_method'] = $route['handler'][1] ?? null;

        return View::render($template, $data);
    }

    /**
     * To display plain text
     * @param string $content
     * @param int $status
     * @param array $headers
     * @return Response
     */
    protected function text(
        string $content,
        int $status = 200,
        array $headers = []
    ): Response {
        $this->response->setStatusCode($status);
        $this->response->addHeader('Content-Type', 'text/plain; charset=UTF-8');

        foreach ($headers as $key => $value) {
            $this->response->addHeader($key, $value);
        }

        return $this->response->setBody($content);
    }

    /**
     * @param array $content
     * @param int $status
     * @param array $headers
     * @return Response
     */
    public function json(
        array $content,
        int $status = 200,
        array $headers = []
    ): Response {
        $this->response->setStatusCode($status);
        $this->response->addHeader('Content-Type', 'application/json; charset=UTF-8');

        foreach ($headers as $key => $value) {
            $this->response->addHeader($key, $value);
        }

        return $this->response->setBody($content);
    }

    /**
     * @return Response
     */
    protected function noContent(): Response
    {
        return $this->response->setStatusCode(204);
    }

    /**
     * @param string $url
     * @param array $messages
     * @return Response
     */
    protected function redirect(string $url, array $messages = []): Response
    {
        foreach ($messages as $key => $message) {
            // keep old input handling as-is
            if ($key === 'old') {
                with_old_input($this->request->getPostData());
                continue;
            }

            $messageText = '';

            if (is_string($message)) {
                $decoded = json_decode($message, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $message = $decoded;
                }
            }

            // If message is a nested array (e.g. ['field' => ['err1','err2']]) flatten it nicely
            if (is_array($message)) {
                // If it looks like field => [messages...] (associative), render with field labels
                $isAssoc = array_values($message) !== $message;

                if ($isAssoc) {
                    $parts = [];
                    foreach ($message as $field => $msgs) {
                        // msgs can be string or array
                        if (is_array($msgs)) {
                            // dedupe and remove empty
                            $msgs = array_values(array_filter(array_unique($msgs), fn($v) => $v !== '' && $v !== null));
                        } else {
                            $msgs = [$msgs];
                        }

                        if (empty($msgs)) continue;

                        // humanize field name: "initialQtyDate" => "Initial Qty Date", "initial_qty" => "Initial Qty"
                        $label = (string)$field;
                        // if field name looks like an input array 'attachments[]' remove brackets
                        $label = preg_replace('/\[\]$/', '', $label);
                        // split camelCase and underscores/dashes into words
                        $label = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $label);
                        $label = str_replace(['_', '-'], ' ', $label);
                        $label = trim($label);
                        $label = ucwords($label);

                        // create a single string of messages for this field
                        $parts[] = "<strong>{$label}:</strong> " . htmlentities(implode('<br>', $msgs));
                    }

                    if (!empty($parts)) {
                        $messageText = implode('<br>', $parts);
                    }
                } else {
                    // Not assoc: simple list of messages. dedupe and join
                    $flat = array_values(array_filter(array_unique($message), fn($v) => $v !== '' && $v !== null));
                    $messageText = implode('<br>', array_map('htmlentities', $flat));
                }
            } elseif (is_string($message)) {
                // simple string — use as-is (escape to be safe)
                $messageText = htmlentities($message);
            } elseif ($message instanceof \Stringable) {
                $messageText = htmlentities((string)$message);
            } else {
                // fallback — convert to string
                $messageText = htmlentities(var_export($message, true));
            }

            // final fallback: if somehow empty, set an empty string
            if ($messageText === '') {
                $messageText = '';
            }

            // store as flash (your SessionManager expects plain string)
            $this->sessionManager->setFlash($key, $messageText);
        }

        if (!str_starts_with($url, 'http')) {
            $url = '/' . ltrim($url, '/');
        }

        return new RedirectResponse($url);
    }
}
