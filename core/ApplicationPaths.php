<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;

/** Central, deterministic filesystem paths for an application using Jnana. */
final class ApplicationPaths
{
    private static ?self $instance = null;
    private string $applicationRoot;
    private string $frameworkRoot;
    private string $vendorRoot;

    /** @param array<string,string> $namespacePaths PSR-4 namespace prefixes mapped to app-relative directories. */
    public function __construct(string $applicationRoot, ?string $frameworkRoot = null, ?string $vendorRoot = null, private array $namespacePaths = ['App\\' => 'app'])
    {
        $root = realpath($applicationRoot);
        if ($root === false || !is_dir($root)) {
            throw new InvalidArgumentException("Application root does not exist: {$applicationRoot}");
        }
        $this->applicationRoot = rtrim($root, DIRECTORY_SEPARATOR);
        $this->frameworkRoot = rtrim($frameworkRoot ?? dirname(__DIR__), DIRECTORY_SEPARATOR);
        $this->vendorRoot = rtrim($vendorRoot ?? $this->applicationRoot . DIRECTORY_SEPARATOR . 'vendor', DIRECTORY_SEPARATOR);
        self::$instance = $this;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \LogicException('ApplicationPaths has not been initialized. Boot the application first.');
        }
        return self::$instance;
    }

    public function applicationRoot(): string { return $this->applicationRoot; }
    public function frameworkRoot(): string { return $this->frameworkRoot; }
    public function vendorRoot(): string { return $this->vendorRoot; }
    public function app(string $path = ''): string { return $this->join($this->applicationRoot, $path); }
    public function config(string $path = ''): string { return $this->app('config' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function storage(string $path = ''): string { return $this->app('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function public(string $path = ''): string { return $this->app('public' . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '')); }
    public function framework(string $path = ''): string { return $this->join($this->frameworkRoot, $path); }
    public function vendor(string $path = ''): string { return $this->join($this->vendorRoot, $path); }

    public function namespaceDirectory(string $namespace): string
    {
        foreach ($this->namespacePaths as $prefix => $relativeDirectory) {
            if (str_starts_with($namespace, $prefix)) {
                $suffix = trim(substr($namespace, strlen($prefix)), '\\');
                $relative = trim($relativeDirectory . ($suffix !== '' ? DIRECTORY_SEPARATOR . str_replace('\\', DIRECTORY_SEPARATOR, $suffix) : ''), DIRECTORY_SEPARATOR);
                return $this->app($relative);
            }
        }
        throw new InvalidArgumentException("No filesystem path is configured for namespace: {$namespace}");
    }

    private function join(string $root, string $path): string
    {
        return $root . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR) : '');
    }
}
