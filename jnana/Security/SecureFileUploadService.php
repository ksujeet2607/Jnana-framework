<?php

declare(strict_types=1);

namespace Jnana\Security;

use Core\ApplicationPaths;
use Core\ConfigManager;
use Jnana\Http\Request;
use Jnana\Http\UploadedFile;
use RuntimeException;
use Throwable;

/** Validates and stores uploaded files outside the public web root. */
final class SecureFileUploadService
{
    public function __construct(
        private readonly ConfigManager $config,
        private readonly ApplicationPaths $paths,
        private readonly SecurityAuditLogger $audit
    ) {}

    /**
     * @return array{path:string,filename:string,mime_type:string,size:int,original_name:string}
     */
    public function store(UploadedFile $file, string $category = 'default', ?Request $request = null): array
    {
        $settings = $this->config->get('Uploads', []);
        if (!is_array($settings) || !($settings['enabled'] ?? true)) {
            throw new RuntimeException('File uploads are disabled.');
        }

        $maxBytes = max(1, (int) ($settings['max_bytes'] ?? 5242880));
        $mimeMap = $settings['allowed_mime_types'] ?? [];
        if (!is_array($mimeMap) || $mimeMap === []) {
            throw new RuntimeException('No upload MIME types are configured.');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]{1,50}$/', $category)) {
            $this->audit->record('upload.rejected', ['reason' => 'invalid_category'], $request);
            throw new RuntimeException('Invalid upload category.');
        }

        if ($file->getError() !== UPLOAD_ERR_OK || !$file->isValid()) {
            $this->audit->record('upload.rejected', ['reason' => 'upload_error', 'upload_error' => $file->getError()], $request);
            throw new RuntimeException('The uploaded file could not be received.');
        }

        $size = $file->getSize();
        if ($size < 1 || $size > $maxBytes) {
            $this->audit->record('upload.rejected', ['reason' => 'size_limit', 'size' => $size], $request);
            throw new RuntimeException('The file is empty or exceeds the configured upload size limit.');
        }

        $tmpPath = $file->getPathname();
        if (!is_file($tmpPath) || !is_readable($tmpPath)) {
            $this->audit->record('upload.rejected', ['reason' => 'missing_temp_file'], $request);
            throw new RuntimeException('The uploaded file is unavailable.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if (!is_string($mime) || !isset($mimeMap[$mime]) || !is_string($mimeMap[$mime])) {
            $this->audit->record('upload.rejected', ['reason' => 'mime_not_allowed', 'detected_mime' => is_string($mime) ? $mime : 'unknown'], $request);
            throw new RuntimeException('This file type is not allowed.');
        }

        $extension = strtolower(ltrim($mimeMap[$mime], '.'));
        if (!preg_match('/^[a-z0-9]{1,10}$/', $extension)) {
            throw new RuntimeException('Invalid extension configured for the detected file type.');
        }

        $relativeDirectory = trim((string) ($settings['directory'] ?? 'uploads'), '/\\');
        if ($relativeDirectory === '' || preg_match('#(^|[\\/])\.\.([\\/]|$)#', $relativeDirectory)) {
            throw new RuntimeException('Upload directory configuration is invalid.');
        }

        $directory = $this->paths->storage($relativeDirectory . DIRECTORY_SEPARATOR . $category);
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to prepare the upload storage directory.');
        }
        $realDirectory = realpath($directory);
        $storageRoot = realpath($this->paths->storage());
        if ($realDirectory === false || $storageRoot === false || !str_starts_with($realDirectory, rtrim($storageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Upload directory must be inside application storage.');
        }

        $filename = bin2hex(random_bytes(24)) . '.' . $extension;
        $destination = $realDirectory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($tmpPath, $destination)) {
            $this->audit->record('upload.failed', ['reason' => 'move_failed', 'mime_type' => $mime], $request);
            throw new RuntimeException('Unable to store the uploaded file.');
        }
        @chmod($destination, 0640);

        $originalName = basename(str_replace('\\', '/', $file->getName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?? 'upload';
        $originalName = (function_exists('mb_substr') ? mb_substr($originalName, 0, 255) : substr($originalName, 0, 255));

        $this->audit->record('upload.accepted', ['mime_type' => $mime, 'size' => $size, 'category' => $category], $request);

        return [
            'path' => $relativeDirectory . '/' . $category . '/' . $filename,
            'filename' => $filename,
            'mime_type' => $mime,
            'size' => $size,
            'original_name' => $originalName,
        ];
    }
}
