<?php

namespace Core;

use RuntimeException;

/**
 * Manages application configuration.
 *
 * Loads /config/Config.php into the root of the config array, then loads every
 * other *.php file in /config/ under a key matching its filename (e.g.
 * /config/db.php becomes $config['db']).
 */
final class ConfigManager
{
    private array $config = [];
    private readonly string $configDir;
    private readonly string $applicationRoot;

    public function __construct(?string $applicationRoot = null)
    {
        $this->applicationRoot = $applicationRoot !== null
            ? (realpath($applicationRoot) ?: rtrim($applicationRoot, DIRECTORY_SEPARATOR))
            : ApplicationPaths::instance()->applicationRoot();
        $this->configDir = rtrim($this->applicationRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'config';
        $rootConfigFile = $this->configDir . DIRECTORY_SEPARATOR . 'Config.php';

        if (!is_dir($this->configDir)) {
            throw new RuntimeException('Configuration directory not found: ' . $this->configDir);
        }

        // Load the root Config.php file (if present), merged directly into the top level.
        if (file_exists($rootConfigFile)) {
            $rootConfig = include $rootConfigFile;
            if ($rootConfig !== null && !is_array($rootConfig)) {
                throw new RuntimeException('Configuration file must return an array or null: ' . $rootConfigFile);
            }
            $this->config = $rootConfig ?? [];
        }

        // Load every other config file under a key matching its filename.
        $files = glob($this->configDir . DIRECTORY_SEPARATOR . '*.php') ?: [];
        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            if (strtolower($filename) === 'config') {
                continue; // Already loaded above.
            }

            $fileConfig = include $file;
            if (!is_array($fileConfig)) {
                throw new RuntimeException("Configuration file must return an array: $file");
            }

            $this->config[$filename] = $fileConfig;
        }

        $this->validateConfig();
    }

    /**
     * Magic getter to retrieve top-level configuration values dynamically.
     *
     * @param string $name The configuration key to retrieve.
     * @return mixed|null The configuration value or null if the key doesn't exist.
     */
    public function __get(string $name): mixed
    {
        return $this->config[$name] ?? null;
    }

    /**
     * Required alongside __get so that isset()/empty() on dynamic properties
     * (e.g. isset($config->db)) work as expected, instead of always returning false.
     */
    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->config);
    }

    /**
     * Get the registered application routes.
     *
     * @return array The array of route namespaces.
     */
    public function getRoutes(): array
    {
        return $this->get('routes', []);
    }

    /**
     * Get dependency injection definitions.
     *
     * @return array The array of DI definitions.
     */
    public function getDefinitions(): array
    {
        return $this->get('definitions', []);
    }

    /**
     * Get database configuration.
     *
     * @return array The database configuration array.
     */
    public function getDatabaseConfig(): array
    {
        return $this->get('db', []);
    }

    /**
     * Whether verbose current-route debug info should be shown.
     *
     * @return bool
     */
    public function getRouteDebugFlag(): bool
    {
        return (bool) $this->get('show_current_route_info', false);
    }

    /**
     * Get a configuration value by key with an optional default value.
     * Supports dot notation for nested keys, e.g. "db.host".
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = $this->config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Validates the configuration settings. Throws on the first problem found
     * so misconfiguration fails fast at boot time rather than surfacing later.
     */
    private function validateConfig(): void
    {
        if (!is_array($this->getRoutes()) || empty($this->getRoutes())) {
            throw new RuntimeException('Invalid routes configuration.');
        }

        $authStrategy = $this->get('auth_strategy');
        if (!$authStrategy) {
            throw new RuntimeException('Auth strategy not specified.');
        }
        if (!in_array($authStrategy, ['database', 'file'], true)) {
            throw new RuntimeException("Auth strategy must be either 'database' or 'file'.");
        }

        if (!is_array($this->getDefinitions())) {
            throw new RuntimeException('Definitions configuration must be an array.');
        }

        if ($this->__isset('db') && !is_array($this->get('db'))) {
            throw new RuntimeException('Database configuration must be an array.');
        }
    }

    public function all(): array
    {
        return $this->config;
    }

    public function applicationRoot(): string
    {
        return $this->applicationRoot;
    }
}