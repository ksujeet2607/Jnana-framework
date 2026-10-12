<?php
if (!function_exists('unlink_file')) {
    function unlink_file(string $filePath): void
    {
        // Normalize: remove leading slash and remove "public/"
        $clean = ltrim($filePath, '/');
        $clean = preg_replace('#^public/#', '', $clean);

        // Split into filename + extension
        $ext = pathinfo($clean, PATHINFO_EXTENSION);
        $name = pathinfo($clean, PATHINFO_FILENAME);
        $dir  = pathinfo($clean, PATHINFO_DIRNAME);

        // Build thumbnail filename (name_thumb.ext)
        // Example: uploads/customers/abc.png → uploads/customers/abc_thumb.png
        $thumb = ($dir !== '.' ? $dir.'/' : '') . $name . '_thumb.' . $ext;

        // List of candidates to delete
        $deleteList = [$clean, $thumb];

        // Convert into absolute paths and delete if exists
        foreach ($deleteList as $relPath) {
            $pathsToCheck = [
                base_path($relPath),
                public_path($relPath),
            ];

            foreach ($pathsToCheck as $absolutePath) {
                if (file_exists($absolutePath)) {
                    unlink($absolutePath);
                }
            }
        }
    }
}
if (!function_exists('get_file_thumbnail')) {
    /**
     * Get or generate a thumbnail for a file.
     * - For images: generate a resized thumbnail (saved as *_thumb.ext).
     * - For known document types (pdf, excel, word, etc.): return SVG icon path.
     * - For others: return a generic file icon.
     *
     * @param string $filePath   Full path to the original file
     * @param int    $width      Desired thumbnail width (for images)
     * @param int    $height     Desired thumbnail height (for images)
     * @return string|null       Thumbnail / icon path (URL or relative path), or null on failure
     */
    function get_file_thumbnail(string $path, int $width = 100, int $height = 100): ?string
    {
        $filePath = str_starts_with(ltrim($path, '/\\'), 'public/')
            ? public_path(substr(ltrim($path, '/\\'), 7))
            : base_path(ltrim($path, '/\\'));
        if (!file_exists($filePath)) {
            return null;
        }

        $info   = pathinfo($filePath);
        $ext    = strtolower($info['extension'] ?? '');
        $dir    = $info['dirname'] ?? '';
        $name   = $info['filename'] ?? '';

        // 1) Image extensions → generate thumbnail
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($ext, $imageExtensions, true)) {
            $thumbPath = $dir . '/' . $name . "_thumb." . $ext;
            $pathComponents = explode('../', $thumbPath);

            // if already exists, just return it
            if (file_exists($thumbPath)) {
                return url(end($pathComponents));
            }

            // detect mime
            $mime = mime_content_type($filePath);

            switch ($mime) {
                case 'image/jpeg':
                case 'image/jpg':
                    $srcImg = imagecreatefromjpeg($filePath);
                    break;
                case 'image/png':
                    $srcImg = imagecreatefrompng($filePath);
                    break;
                case 'image/gif':
                    $srcImg = imagecreatefromgif($filePath);
                    break;
                case 'image/webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $srcImg = imagecreatefromwebp($filePath);
                    } else {
                        return null;
                    }
                    break;
                default:
                    return null; // unsupported image mime
            }

            if (!$srcImg) {
                return null;
            }

            $origWidth  = imagesx($srcImg);
            $origHeight = imagesy($srcImg);

            // maintain aspect ratio
            $ratio = min($width / $origWidth, $height / $origHeight);
            $newWidth  = (int)($origWidth * $ratio);
            $newHeight = (int)($origHeight * $ratio);

            $thumbImg = imagecreatetruecolor($newWidth, $newHeight);

            // preserve transparency for png/gif/webp
            if (in_array($mime, ['image/png', 'image/gif', 'image/webp'], true)) {
                imagecolortransparent($thumbImg, imagecolorallocatealpha($thumbImg, 0, 0, 0, 127));
                imagealphablending($thumbImg, false);
                imagesavealpha($thumbImg, true);
            }

            imagecopyresampled(
                $thumbImg,
                $srcImg,
                0,
                0,
                0,
                0,
                $newWidth,
                $newHeight,
                $origWidth,
                $origHeight
            );

            switch ($mime) {
                case 'image/jpeg':
                case 'image/jpg':
                    imagejpeg($thumbImg, $thumbPath, 85);
                    break;
                case 'image/png':
                    imagepng($thumbImg, $thumbPath);
                    break;
                case 'image/gif':
                    imagegif($thumbImg, $thumbPath);
                    break;
                case 'image/webp':
                    if (function_exists('imagewebp')) {
                        imagewebp($thumbImg, $thumbPath);
                    }
                    break;
            }

            imagedestroy($srcImg);
            imagedestroy($thumbImg);

            return url(end($pathComponents));
        }

        // 2) Non-image files → return PNG icon path
        // Adjust this base path to where your PNG icons live (URL or relative to public root)
        $iconBasePath = '/public/icons/files'; // e.g. /public/icons/files/

        // Map extension groups to specific icons
        $extToType = [
            // PDF
            'pdf'  => 'pdf',

            // Word
            'doc'  => 'doc',
            'docx' => 'doc',

            // Excel / spreadsheets
            'xls'  => 'xls',
            'xlsx' => 'xls',
            'csv'  => 'xls',

            // PowerPoint
            'ppt'  => 'ppt',
            'pptx' => 'ppt',

            // Archives
            'zip'  => 'zip',
            'rar'  => 'zip',
            '7z'   => 'zip',
            'tar'  => 'zip',
            'gz'   => 'zip',

            // Text / code
            'txt'  => 'text',
            'log'  => 'text',
            'md'   => 'text',
            'json' => 'text',
            'xml'  => 'script',
            'html' => 'script',
            'css'  => 'script',
            'js'   => 'script',
            'php'  => 'script',
        ];

        $type = $extToType[$ext] ?? 'file'; // fallback: generic file icon

        $iconPath = rtrim($iconBasePath, '/') . '/' . $type . '.png';

        return $iconPath;
    }
}
