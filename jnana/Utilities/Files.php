<?php

namespace Jnana\Utilities;

/**
 * File upload helper
 *
 * Usage:
 *   $u = (new Files())
 *        ->file('attachments', 0)         // $_FILES['attachments'], index 0 for multiple
 *        ->dir('/var/www/project/public/uploads/customers/')
 *        ->allowedExts(['jpg','png','pdf'])
 *        ->allowedType(['image/jpeg','image/png','application/pdf'])
 *        ->maxSize(10240)                // KB (default 10240 KB = 10 MB)
 *        ->quality(80)                   // optional image compression quality 1-100 (only for images)
 *        ->uploadFile();                 // returns ['status'=>'success','path'=>'uploads/...'] or ['status'=>'error','error'=>...]
 */
class Files
{
    protected string $file; // key in $_FILES
    protected int $fileindex = 0; // index for multiple files
    protected string $folder = ''; // destination directory - must be absolute and end with '/'
    protected string $filename_custom = ''; // custom filename *without* extension
    protected int $maxsize = 10240; // KB (default 10 MB)
    protected int $minsize = 1; // KB
    protected array $allowed_type = []; // MIME types
    protected array $allowed_exts = []; // extensions (lowercase, without dot)
    protected ?int $quality = null; // image quality 1-100

    public function __construct()
    {
        // sensible defaults
        $this->allowed_exts = ['jpeg', 'jpg', 'png', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'pdf', 'zip', 'rar', '7z', 'tar'];
        $this->allowed_type = [
            'image/jpg', 'image/jpeg', 'image/png', 'text/plain', 'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/pdf',
            'application/zip', 'application/x-7z-compressed', 'application/x-tar'
        ];
    }

    public function file(string $file, int $fileindex = 0): self
    {
        $this->file = $file;
        $this->fileindex = $fileindex;
        return $this;
    }

    /**
     * Set absolute destination directory. Must be writeable. Trailing slash will be added if missing.
     */
    public function dir(string $dir): self
    {
        $dir = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR;
        $this->folder = $dir;
        return $this;
    }

    public function customName(string $name): self
    {
        $this->filename_custom = $name;
        return $this;
    }

    /**
     * Max size in KB
     */
    public function maxSize(int $sizeKb): self
    {
        $this->maxsize = $sizeKb;
        return $this;
    }

    /**
     * Min size in KB
     */
    public function minSize(int $sizeKb): self
    {
        $this->minsize = $sizeKb;
        return $this;
    }

    public function allowedExts(array $exts): self
    {
        $this->allowed_exts = array_map(fn($e) => strtolower(trim($e, '. ')), $exts);
        return $this;
    }

    public function allowedType(array $types): self
    {
        $this->allowed_type = $types;
        return $this;
    }

    public function quality(int $quality): self
    {
        $q = max(1, min(100, $quality));
        $this->quality = $q;
        return $this;
    }

    /**
     * Upload file. Returns array:
     *  - success: ['status'=>'success', 'path' => '<relative or provided path>']
     *  - error:   ['status'=>'error', 'error' => '...']
     *
     * If $validate is false, validation is skipped.
     */
    public function uploadFile(bool $validate = true): array
    {
        // check basic config
        if (empty($this->file)) {
            return ['status' => 'error', 'error' => 'No file key specified'];
        }
        // ensure destination folder set
        if (empty($this->folder)) {
            return ['status' => 'error', 'error' => 'Destination directory not set. Call dir() with an absolute path.'];
        }

        // ensure folder exists
        if (!is_dir($this->folder)) {
            if (!@mkdir($this->folder, 0777, true)) {
                return ['status' => 'error', 'error' => "Failed to create upload directory: {$this->folder}"];
            }
        }

        // ensure file exists in $_FILES
        if (!isset($_FILES[$this->file])) {
            return ['status' => 'error', 'error' => "Input file '{$this->file}' not found in \$_FILES"];
        }

        $isArray = is_array($_FILES[$this->file]['name']);
        $name = $isArray ? ($_FILES[$this->file]['name'][$this->fileindex] ?? '') : ($_FILES[$this->file]['name'] ?? '');
        $tmpName = $isArray ? ($_FILES[$this->file]['tmp_name'][$this->fileindex] ?? '') : ($_FILES[$this->file]['tmp_name'] ?? '');
        $size = $isArray ? ($_FILES[$this->file]['size'][$this->fileindex] ?? 0) : ($_FILES[$this->file]['size'] ?? 0);
        $errorCode = $isArray ? ($_FILES[$this->file]['error'][$this->fileindex] ?? UPLOAD_ERR_OK) : ($_FILES[$this->file]['error'] ?? UPLOAD_ERR_OK);

        if ($name === '' || $tmpName === '' || $errorCode !== UPLOAD_ERR_OK) {
            return ['status' => 'error', 'error' => 'No uploaded file found or upload error code: ' . $errorCode];
        }

        // get extension
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
        if ($ext === '') {
            return ['status' => 'error', 'error' => 'Uploaded file has no extension'];
        }

        // prepare filename
        $filename = $this->filename_custom !== '' ? $this->filename_custom . '.' . $ext : $this->generateRandomString(12) . '_' . time() . '.' . $ext;
        // sanitize filename (keep extension)
        $filename = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $filename);

        // validation
        if ($validate) {
            [$err, $errTxt] = $this->validate($ext, $tmpName, $size);
            if ($err > 0) {
                return ['status' => 'error', 'error' => $errTxt];
            }
        }

        // move uploaded file to destination
        $destination = $this->folder . $filename;
        // Use move_uploaded_file for normal uploaded files, fallback to rename/copy for other cases
        $moved = false;
        if (is_uploaded_file($tmpName)) {
            $moved = @move_uploaded_file($tmpName, $destination);
        }
        if (!$moved) {
            // try rename/copy
            $moved = @rename($tmpName, $destination);
            if (!$moved) {
                $moved = @copy($tmpName, $destination);
            }
        }
        if (!$moved) {
            return ['status' => 'error', 'error' => 'Failed to move uploaded file to destination'];
        }

        // Optional image compression
        if ($this->quality !== null) {
            try {
                $this->compressIfImage($destination, (int)$this->quality);
            } catch (\Throwable $e) {
                // compression failure is non-fatal, but log or return error if you prefer
                // we'll just continue silently
            }
        }

        // Return relative path (strip server absolute prefix if you want)
        // Caller can decide how to store path. Here we return full path and relative basename.
        return ['status' => 'success', 'path' => $destination, 'basename' => $filename];
    }

    /**
     * Validate extension, mime and size.
     * @param string $extn extension lowercase
     * @param string $tmp_name
     * @param int|null $sizeBytes (pass bytes; if null will try to read from $_FILES)
     * @return array [$errorCount, $errorText]
     */
    protected function validate(string $extn, string $tmp_name, ?int $sizeBytes = null): array
    {
        $error = 0;
        $error_txt = '';

        // size in bytes -> convert to KB
        if ($sizeBytes === null) {
            $isArray = is_array($_FILES[$this->file]['size'] ?? null);
            $sizeBytes = $isArray ? ($_FILES[$this->file]['size'][$this->fileindex] ?? 0) : ($_FILES[$this->file]['size'] ?? 0);
        }
        $sizeKb = (int) round($sizeBytes / 1024);

        if ($sizeKb < $this->minsize) {
            $error++;
            $error_txt .= "File should be minimum " . round($this->minsize / 1024, 2) . " MB in size.\n";
        }
        if ($sizeKb > $this->maxsize) {
            $error++;
            $error_txt .= "File up to " . round($this->maxsize / 1024, 2) . " MB is allowed.\n";
        }

        // MIME detection
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp_name) ?: '';

        if (!empty($this->allowed_type) && !in_array($mime, $this->allowed_type, true)) {
            $error++;
            $error_txt .= "Only " . implode(", ", $this->allowed_exts) . " file types allowed (MIME). Provided: " . strtolower($mime) . "\n";
        }

        // extension validation
        if (!empty($this->allowed_exts) && !in_array(strtolower($extn), $this->allowed_exts, true)) {
            $error++;
            $error_txt .= "Only " . implode(", ", $this->allowed_exts) . " file extensions allowed. Provided: " . strtolower($extn) . "\n";
        }

        return [$error, trim($error_txt)];
    }

    /**
     * Compress image at $path if it's a supported image type. Non-images are ignored.
     * This will overwrite original file.
     */
    protected function compressIfImage(string $path, int $quality): void
    {
        if (!file_exists($path)) {
            return;
        }

        $info = @getimagesize($path);
        if ($info === false || empty($info['mime'])) {
            return;
        }

        $mime = $info['mime'];
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = @imagecreatefromjpeg($path);
                if (!$image) return;
                // handle orientation if exif available
                if (function_exists('exif_read_data')) {
                    try {
                        $exif = @exif_read_data($path);
                        if (!empty($exif['Orientation'])) {
                            switch ($exif['Orientation']) {
                                case 3: $image = imagerotate($image, 180, 0); break;
                                case 6: $image = imagerotate($image, -90, 0); break;
                                case 8: $image = imagerotate($image, 90, 0); break;
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore exif errors
                    }
                }
                // quality: 0-100
                imagejpeg($image, $path, $quality);
                imagedestroy($image);
                break;

            case 'image/png':
                $image = @imagecreatefrompng($path);
                if (!$image) return;
                // quality (PNG uses compression level 0-9; 0 = no compression (best quality), 9 = max compression (lowest quality))
                // map quality 1-100 to compression 0-9: higher quality => lower compression
                $pngCompression = (int) round((100 - $quality) / 11); // maps 0..100 -> ~0..9
                $pngCompression = max(0, min(9, $pngCompression));
                // preserve alpha
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, $path, $pngCompression);
                imagedestroy($image);
                break;

            case 'image/gif':
                $image = @imagecreatefromgif($path);
                if (!$image) return;
                imagegif($image, $path);
                imagedestroy($image);
                break;

            default:
                // not an image we handle
                return;
        }
    }

    protected function generateRandomString(int $length = 12): string
    {
        // random bytes -> bin2hex to get readable string
        $bytes = random_bytes((int)ceil($length / 2));
        return substr(bin2hex($bytes), 0, $length);
    }
}
