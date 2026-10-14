<?php

namespace Jnana\Security;

use Jnana\Http\Request;

class SecurityService
{
    private string $salt;
    private string $algorithm;
    private array $csrfExclusionRoutes;
    private array $csrfExclusionMethods;
    private string $formTokenLabel = 'easy-csrf-token';
    private string $sessionTokenLabel = 'EASY_CSRF_TOKEN_SESS_IDX';
    private int $tokenLifetime = 7200; // 2 hours
    private string $sessionTokenTimeLabel = 'EASY_CSRF_TOKEN_TIME';
    private array $post;
    private array $session;
    private array $server;

    public function __construct(
        array $securityConfig,
        array|null $post = null,
        array|null $session = null,
        array|null $server = null
    ) {
        $this->salt = $securityConfig['salt'] ?? '';
        $this->algorithm = $securityConfig['algorithm'] ?? 'sha256';
        $this->csrfExclusionRoutes = $securityConfig['csrf_exclusion_routes'] ?? [];
        $this->csrfExclusionMethods = $securityConfig['csrf_exclusion_methods'] ?? [];

        $this->post = $post ?? $_POST;
        $this->server = $server ?? $_SERVER;

        if ($session) {
            $this->session = $session;
        } elseif (isset($_SESSION)) {
            $this->session = &$_SESSION;
        } else {
            throw new \RuntimeException('No session available for persistence.');
        }
    }

    /**
     * Get excluded methods for CSRF validation.
     */
    public function getExcludedMethods(): array
    {
        return $this->csrfExclusionMethods;
    }

    /**
     * Get excluded methods for CSRF validation.
     */
    public function getExcludedRoutes(): array
    {
        return $this->csrfExclusionRoutes;
    }

    /**
     * Generate and store the CSRF token in the session.
     */
    public function getCSRFToken(): string
    {
        if (
            empty($this->session[$this->sessionTokenLabel]) ||
            empty($this->session[$this->sessionTokenTimeLabel]) ||
            (time() - $this->session[$this->sessionTokenTimeLabel]) > $this->tokenLifetime
        ) {
            $this->session[$this->sessionTokenLabel] = bin2hex(random_bytes(32));
            $this->session[$this->sessionTokenTimeLabel] = time();
        }

        return $this->generateToken($this->session[$this->sessionTokenLabel]);
    }

    /**
     * Validate CSRF token for the given method and URI.
     */
    public function validate(string $method, string $uri, ?Request $request): bool
    {
        // Skip validation for excluded routes or methods
        if (in_array($uri, $this->csrfExclusionRoutes, true) || in_array($method, $this->csrfExclusionMethods, true)) {
            return true;
        }

        return $this->isValidRequest($request);
    }

    /**
     * Insert a hidden CSRF token input field into forms.
     */
    public function insertHiddenToken(bool $echo = false): string
    {
        $csrfToken = $this->getCSRFToken();
        $hiddenInput = sprintf(
            '<input type="hidden" name="%s" value="%s" />',
            $this->xssafe($this->formTokenLabel),
            $this->xssafe($csrfToken)
        );

        if ($echo) {
            echo $hiddenInput;
        }

        return $hiddenInput;
    }

    /**
     * Unset the CSRF token from the session.
     */
    public function unsetToken(): void
    {
        unset($this->session[$this->sessionTokenLabel]);
    }

    /**
     * Escape data for XSS mitigation.
     */
    public function xssafe($data, string $encoding = 'UTF-8'): string
    {
        return htmlspecialchars((string)$data, ENT_QUOTES | ENT_HTML401, $encoding);
    }

    /**
     * Validate the CSRF token sent in the request.
     */
    private function isValidRequest(?Request $request): bool
    {
        if (
            empty($this->session[$this->sessionTokenLabel]) ||
            empty($this->session[$this->sessionTokenTimeLabel])
        ) {
            return false;
        }

        // Check expiry
        if ((time() - $this->session[$this->sessionTokenTimeLabel]) > $this->tokenLifetime) {
            return false;
        }

        $csrfToken = $this->post[$this->formTokenLabel] ?? null;

        if (!$csrfToken && $request) {
            $csrfToken = $request->getHeader('X-Csrf-Token');
        }

        if (!$csrfToken || !is_string($csrfToken)) {
            return false;
        }

        $expectedToken = $this->generateToken($this->session[$this->sessionTokenLabel]);

        return hash_equals($csrfToken, $expectedToken);
    }

    /**
     * Generate a token using HMAC with optional IP-based salting.
     */
    private function generateToken(string $baseToken): string
    {
        $data = $this->salt;
        return hash_hmac($this->algorithm, $data, $baseToken);
    }
}

