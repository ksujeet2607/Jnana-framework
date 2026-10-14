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
        $filename = $name ?: $this->getName();

        $path = rtrim($directory, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!move_uploaded_file($this->getPathname(), $path)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        return $path;
    }
}