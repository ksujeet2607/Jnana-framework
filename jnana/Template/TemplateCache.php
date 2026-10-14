<?php

namespace Jnana\Template;

final class TemplateCache
{
    public function __construct(
        private string $path,
        private bool $enabled
    ) {
        $this->path = rtrim($this->path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    public function get(
        string $view,
        callable $compiler
    ): string {

        if (!is_writable($this->path)) {
            throw new \RuntimeException("Cache path not writable: {$this->path}");
        }

        $cacheFile = $this->path . md5($view) . '.php';

        if (!$this->enabled || !$this->isFresh($cacheFile, $view)) {
            [$code, $deps] = $compiler();

            file_put_contents(
                $cacheFile,
                "<?php\n/* compiled */\n?>" . $code,
                LOCK_EX
            );

            file_put_contents(
                $cacheFile . '.deps',
                serialize($deps)
            );

            if (function_exists('opcache_invalidate')) {
                opcache_invalidate($cacheFile, true);
            }
        }

        return $cacheFile;
    }

    private function isFresh(string $cacheFile, string $view): bool
    {
        if (!file_exists($cacheFile)) return false;

        $depsFile = $cacheFile . '.deps';
        if (!file_exists($depsFile)) return false;

        $deps = unserialize(file_get_contents($depsFile));

        foreach ($deps as $dep) {
            if (filemtime(base_path($dep)) > filemtime($cacheFile)) {
                return false;
            }
        }

        return true;
    }
}
