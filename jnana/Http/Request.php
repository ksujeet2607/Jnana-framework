<?php

namespace Jnana\Http;

use Jnana\Validation\BaseValidator;

/**
 * Class Request
 *
 * Represents the current HTTP request and provides
 * safe, structured access to:
 * - Headers
 * - Query parameters
 * - Post data
 * - JSON payload
 * - Files
 * - Server environment
 * - Request attributes (middleware / routing)
 */
class Request
{
    private string $method;
    private string $uri;
    private array $headers = [];
    private array $attributes = [];

    private array $queryParams = [];
    private array $postData = [];
    private array $files = [];
    private array $server = [];

    private ?array $jsonBody = null;
    private bool $jsonParsed = false;

    private ?BaseValidator $validator = null;
    private array $validatedData = [];

    public function __construct()
    {
        $this->server = $_SERVER ?? [];

        $this->method = $this->detectMethod();
        $this->uri = strtok($this->server['REQUEST_URI'] ?? '/', '?');

        $this->headers = $this->normalizeHeaders(
            function_exists('getallheaders') ? getallheaders() : []
        );

        $this->queryParams = $_GET ?? [];
        $this->postData = $_POST ?? [];
        $this->files = $_FILES ?? [];
    }

    /**
     * Returns the clean request path (without query string).
     *
     * Examples:
     *  /invoice/create?x=1 → invoice/create
     *  / → /
     */
    public function path(): string
    {
        // Remove query string (?x=1)
        $path = $this->getUri();

        // Normalize slashes
        $path = trim($path, '/');

        // Root case
        return $path === '' ? '/' : $path;
    }

    /* ---------------------------------------------------------
     | Core request info
     |---------------------------------------------------------- */

    /**
     * Get HTTP method (GET, POST, PUT, DELETE, etc.)
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get request URI without query string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get full URI including query string
     */
    public function getFullUri(): string
    {
        $query = http_build_query($this->queryParams);
        return $query ? "{$this->uri}?{$query}" : $this->uri;
    }

    /* ---------------------------------------------------------
     | Headers
     |---------------------------------------------------------- */

    /**
     * Normalize headers to lowercase keys
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $key => $value) {
            $normalized[strtolower($key)] = $value;
        }
        return $normalized;
    }

    /**
     * Get all request headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get a single header value (case-insensitive)
     */
    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /**
     * Determine if client expects JSON response
     */
    public function expectsJson(): bool
    {
        return str_contains($this->getHeader('accept') ?? '', 'application/json');
    }

    /* ---------------------------------------------------------
     | Query & body parameters
     |---------------------------------------------------------- */

    public function getQueryParams(): array
    {
        return $this->queryParams;
    }

    public function getQueryParam(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function getPostData(): array
    {
        return $this->postData;
    }

    public function getPostParam(string $key, mixed $default = null): mixed
    {
        return $this->postData[$key] ?? $default;
    }

    /**
     * Unified input accessor (POST > GET)
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->postData[$key]
            ?? $this->queryParams[$key]
            ?? $default;
    }

    /**
     * Get raw request body
     */
    public function getRawBody(): string
    {
        return file_get_contents('php://input');
    }

    /**
     * Parse and return JSON request body (cached)
     */
    public function getJsonBody(): ?array
    {
        if ($this->jsonParsed) {
            return $this->jsonBody;
        }

        $this->jsonParsed = true;

        if (!str_starts_with($this->getHeader('content-type') ?? '', 'application/json')) {
            return null;
        }

        $data = json_decode($this->getRawBody(), true);
        $this->jsonBody = is_array($data) ? $data : null;

        return $this->jsonBody;
    }

    /* ---------------------------------------------------------
     | Files & server
     |---------------------------------------------------------- */

    public function getFiles(): array
    {
        return $this->files;
    }

    public function getServer(): array
    {
        return $this->server;
    }

    /* ---------------------------------------------------------
     | Request type helpers
     |---------------------------------------------------------- */

    public function isAjax(): bool
    {
        return strcasecmp(
                $this->getHeader('x-requested-with') ?? '',
                'XMLHttpRequest'
            ) === 0;
    }

    public function isApiRequest(): bool
    {
        return str_starts_with($this->uri, '/api') || $this->expectsJson();
    }

    /* ---------------------------------------------------------
     | Attributes (routing / middleware)
     |---------------------------------------------------------- */

    /**
     * Set a request attribute (used by router/middleware)
     */
    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Set multiple attributes
     */
    public function setAttributes(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /* ---------------------------------------------------------
     | Client info
     |---------------------------------------------------------- */

    /**
     * Get client IP address (basic proxy-aware)
     */
    public function getClientIp(): string
    {
        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', $this->server['HTTP_X_FORWARDED_FOR'])[0]);
        }

        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /* ---------------------------------------------------------
     | Internal helpers
     |---------------------------------------------------------- */

    /**
     * Detect HTTP method with override support
     */
    private function detectMethod(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            if (!empty($_POST['_method'])) {
                return strtoupper($_POST['_method']);
            }

            if (!empty($this->server['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                return strtoupper($this->server['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        return $method;
    }

    /**
     * Get all request input (POST + GET + JSON)
     */
    public function all(): array
    {
        if ($json = $this->getJsonBody()) {
            return $json;
        }

        return array_merge($this->queryParams, $this->postData);
    }

    /**
     * Get validated data only
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Check if validation failed
     */
    public function hasValidationErrors(): bool
    {
        return $this->validator?->hasErrors() ?? false;
    }

    private function makeInlineValidator(array $rules): BaseValidator
    {
        return new class($rules) extends BaseValidator {
            public function __construct(array $rules)
            {
                $this->rules = $rules;
            }
        };
    }



    public function validate(
        string|BaseValidator|array $validator,
        bool $throw = false
    ): array {
        $data = $this->all();

        // ----------------------------------
        // 1. Resolve validator
        // ----------------------------------
        if (is_string($validator)) {
            $validator = app($validator);
        }

        if (is_array($validator)) {
            $validator = $this->makeInlineValidator($validator);
        }

        if (!$validator instanceof BaseValidator) {
            throw new \InvalidArgumentException('Invalid validator provided.');
        }

        $this->validator = $validator;

        // ----------------------------------
        // 2. Execute validation
        // ----------------------------------
        $errors = $validator->validate($data);

        if (!empty($errors)) {
            if ($throw) {
                throw new \RuntimeException('Validation failed.');
            }
            return $errors;
        }

        // ----------------------------------
        // 3. Store validated data
        // ----------------------------------
        $this->validatedData = $validator->validated();

        return [];
    }

    public function getFile(string $key): ?UploadedFile
    {
        if (!isset($this->files[$key])) {
            return null;
        }

        $file = $this->files[$key];

        if (!is_array($file)) {
            return null;
        }

        return new UploadedFile($file);
    }

    public function hasFile(string $key): bool
    {
        return isset($this->files[$key])
            && ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
    }

}
