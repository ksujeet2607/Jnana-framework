<?php

namespace Jnana\Http;

class UploadedFile
{
    public function __construct(
        private array $file
    ) {
    }

    public function getName(): string
    {
        return $this->file['name'] ?? '';
    }

    public function getMimeType(): string
    {
        return $this->file['type'] ?? '';
    }

    public function getSize(): int
    {
        return (int)($this->file['size'] ?? 0);
    }

    public function getPathname(): string
    {
        return $this->file['tmp_name'] ?? '';
    }

    public function getError(): int
    {
        return (int)($this->file['error'] ?? UPLOAD_ERR_NO_FILE);
    }

    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK
            && is_uploaded_file($this->getPathname());
    }

    public function move(string $directory, ?string $name = null): string
    {
        $filename = $name ?: basename(str_replace('\\', '/', $this->getName()));
        if ($filename === '' || $filename === '.' || $filename === '..'
            || str_contains($filename, '/') || str_contains($filename, '\\')
            || preg_match('/[\x00-\x1F\x7F]/', $filename)) {
            throw new \InvalidArgumentException('Invalid uploaded filename.');
        }
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new \RuntimeException('Upload destination must exist and be writable.');
        }

        $path = rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!move_uploaded_file($this->getPathname(), $path)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return $path;
    }
}