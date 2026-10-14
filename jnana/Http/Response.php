<?php

namespace Jnana\Http;

use Jnana\Session\SessionManager;

/**
 * Class Response
 *
 * Represents an HTTP response object responsible for:
 * - Status codes
 * - Headers
 * - Body output
 * - JSON responses
 * - Redirects
 * - File streaming
 * - Flash messages
 */
class Response
{
    /** HTTP status code */
    private int $statusCode = 200;

    /** HTTP headers */
    private array $headers = [];

    /** Response body */
    private mixed $body = null;

    /** Cache hit indicator (useful for debugging / headers) */
    private bool $cacheHit = false;

    /** File streaming flags */
    private bool $isFileStreaming = false;
    private ?string $filePath = null;
    private ?string $customFileName = null;

    /* ---------------------------------------------------------
     | Core setters / getters
     |---------------------------------------------------------- */

    /**
     * Set HTTP status code
     */
    public function setStatusCode(int $statusCode): self
    {
        $this->statusCode = $statusCode;
        return $this;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Add or overwrite a header
     */
    public function addHeader(string $key, string $value): self
    {
        $this->headers[$key] = $value;
        return $this;
    }

    /**
     * Get all headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set response body
     */
    public function setBody(mixed $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get response body
     */
    public function getBody(): mixed
    {
        return $this->body;
    }

    /* ---------------------------------------------------------
     | Cache helpers
     |---------------------------------------------------------- */

    public function setCacheHit(bool $hit): self
    {
        $this->cacheHit = $hit;
        return $this;
    }

    public function isCacheHit(): bool
    {
        return $this->cacheHit;
    }

    /* ---------------------------------------------------------
     | Response helpers
     |---------------------------------------------------------- */

    /**
     * JSON response helper
     */
    public function json(array|object $data, int $statusCode = 200): self
    {
        $this->addHeader('Content-Type', 'application/json');
        return $this->setBody($data, $statusCode);
    }

    /**
     * Redirect response helper
     */
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->setStatusCode($statusCode);
        $this->addHeader('Location', $url);
        $this->body = null;

        return $this;
    }

    /**
     * Empty / No content response
     */
    public function noContent(): self
    {
        $this->statusCode = 204;
        $this->body = null;
        return $this;
    }

    /* ---------------------------------------------------------
     | Flash helpers
     |---------------------------------------------------------- */

    /**
     * Attach success flash message
     */
    public function withSuccess(string $message): self
    {
        if ($message) {
            app(SessionManager::class)->setFlash('success', $message);
        }
        return $this;
    }

    /**
     * Attach error flash message
     *
     * NOTE:
     * SessionManager::setFlash accepts ONLY strings.
     * Arrays (validation errors) are JSON-encoded here
     * and decoded later in redirect formatter.
     */
    public function withError(string|\Throwable|array $message): self
    {
        if ($message instanceof \Throwable) {
            $message = $message->getMessage();
        }

        if (is_array($message)) {
            /**
             * Encode validation error array as JSON string.
             * Do NOT format here.
             */
            $message = json_encode($message, JSON_UNESCAPED_UNICODE);
        }

        if ($message !== '' && $message !== null) {
            app(SessionManager::class)->setFlash('error', (string)$message);
        }

        return $this;
    }



    /* ---------------------------------------------------------
     | File streaming
     |---------------------------------------------------------- */

    /**
     * Enable file download / streaming
     */
    public function download(
        string $filePath,
        ?string $customFileName = null,
        ?string $mimeType = null
    ): self
    {
        $this->isFileStreaming = true;
        $this->filePath = $filePath;
        $this->customFileName = $customFileName;
        $this->headers['__mime_type'] = $mimeType;

        return $this;
    }

    public function downloadXml(
        string $file
    ): self
    {
        return $this->download(
            $file,
            basename($file),
            'application/xml'
        );
    }

    public function downloadPdf(
        string $file
    ): self
    {
        return $this->download(
            $file,
            basename($file),
            'application/pdf'
        );
    }

    public function downloadExcel(
        string $file
    ): self
    {
        return $this->download(
            $file,
            basename($file),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
    }

    public function downloadZip(
        string $file
    ): self
    {
        return $this->download(
            $file,
            basename($file),
            'application/zip'
        );
    }

    public function isFileStreaming(): bool
    {
        return $this->isFileStreaming;
    }

    /* ---------------------------------------------------------
     | Response sender (finalizer)
     |---------------------------------------------------------- */

    /**
     * Send response to the browser
     */
    public function send(): void
    {
        if (headers_sent()) {
            throw new \RuntimeException('Headers already sent. Cannot send response.');
        }

        if ($this->isFileStreaming) {
            $this->streamFile();
            return;
        }

        http_response_code($this->statusCode);

        foreach ($this->headers as $key => $value) {
            header("{$key}: {$value}");
        }

        if ($this->body === null) {
            return;
        }

        if (is_array($this->body) || is_object($this->body)) {
            echo json_encode($this->body, JSON_THROW_ON_ERROR);
            return;
        }

        echo (string) $this->body;
    }

    /* ---------------------------------------------------------
     | Internal helpers
     |---------------------------------------------------------- */

    /**
     * Stream file securely in chunks
     */
    private function streamFile(): void
    {
        if (!$this->filePath || !is_file($this->filePath)) {
            throw new \RuntimeException("File not found: {$this->filePath}");
        }

        $fileName = $this->customFileName ?: basename($this->filePath);
        $mimeType = $this->headers['__mime_type']
            ?? mime_content_type($this->filePath)
            ?? 'application/octet-stream';

        unset($this->headers['__mime_type']);

        http_response_code(200);

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($this->filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');

        if (ob_get_level()) {
            ob_end_clean();
        }

        $handle = fopen($this->filePath, 'rb');

        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }

        fclose($handle);
    }




}
