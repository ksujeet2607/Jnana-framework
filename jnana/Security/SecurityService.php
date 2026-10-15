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
        $this->csrfExclusionMethods = array_map('strtoupper', $securityConfig['csrf_exclusion_methods'] ?? ['GET', 'HEAD', 'OPTIONS']);
        $this->tokenLifetime = max(60, (int) ($securityConfig['csrf_token_lifetime'] ?? 7200));

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
        if (in_array($uri, $this->csrfExclusionRoutes, true)
            || in_array(strtoupper($method), $this->csrfExclusionMethods, true)) {
            return true;
        }

        return $this->isValidRequest($request);
    }

    /**
     * Whether the CSRF token has expired, including a lost session token when
     * the client presents a token from an older page. Such requests remain
     * rejected; the distinction only lets the client recover the token.
     */
    public function isTokenExpired(?Request $request = null): bool
    {
        $issuedAt = $this->session[$this->sessionTokenTimeLabel] ?? null;
        if (is_numeric($issuedAt)) {
            return (time() - (int) $issuedAt) > $this->tokenLifetime;
        }

        $presented = $this->post[$this->formTokenLabel] ?? null;
        if ((!is_string($presented) || $presented === '') && $request) {
            $presented = $request->getHeader('X-CSRF-Token');
        }

        return is_string($presented) && $presented !== '';
    }

    /** Force-issue a fresh CSRF token for the current session. */
    public function refreshToken(): string
    {
        unset($this->session[$this->sessionTokenLabel], $this->session[$this->sessionTokenTimeLabel]);
        return $this->getCSRFToken();
    }

    public function getTokenLifetime(): int
    {
        return $this->tokenLifetime;
    }

    public function getTokenFieldName(): string
    {
        return $this->formTokenLabel;
    }

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
            empty($this->session[$this->sessionTokenTimeLabel]) ||
            $this->isTokenExpired()
        ) {
            return false;
        }

        $csrfToken = $this->post[$this->formTokenLabel] ?? null;
        if ((!is_string($csrfToken) || $csrfToken === '') && $request) {
            $csrfToken = $request->getHeader('X-CSRF-Token');
        }

        if (!is_string($csrfToken) || $csrfToken === '') {
            return false;
        }

        return hash_equals(
            $this->generateToken($this->session[$this->sessionTokenLabel]),
            $csrfToken
        );
    }

    private function generateToken(string $baseToken): string
    {
        $data = $this->salt;
        return hash_hmac($this->algorithm, $data, $baseToken);
    }
}

