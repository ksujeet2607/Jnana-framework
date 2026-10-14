<?php

namespace Core;

use DI\Container;
use DI\ContainerBuilder;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Jnana\Contracts\ServiceProviderInterface;
use Jnana\Database\Database;
use Jnana\Database\DatabaseInterface;
use Jnana\Database\NullDatabase;
use Jnana\Database\NullEntityManager;
use Jnana\Security\AuthStrategyInterface;
use Jnana\Security\DatabaseAuthStrategy;
use Jnana\Security\FileAuthStrategy;
use Jnana\Security\SecurityService;
use PDO;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Contracts\Cache\CacheInterface;

final class ContainerFactory
{
    public static function create(ConfigManager $config, LoggerInterface $logger): Container
    {
        $paths = new ApplicationPaths($config->applicationRoot(), dirname(__DIR__));
        $containerBuilder = new ContainerBuilder();

        $containerBuilder->useAttributes(true);
        $containerBuilder->useAutowiring(true);

        self::registerCoreDefinitions($containerBuilder, $config, $logger, $paths);
        self::registerAuth($containerBuilder, $config, $logger);
        self::registerEntityManager($containerBuilder, $logger, $paths);
        self::registerCache($containerBuilder);

        self::applyManualBindings($containerBuilder, $config, $logger);
        self::registerProviders($containerBuilder, $config, $logger, $paths);
        self::registerValidators($containerBuilder, $logger, $paths);

        $logger->info('Container created successfully.');

        return $containerBuilder->build();
    }

    private static function registerCoreDefinitions(ContainerBuilder $containerBuilder, ConfigManager $config, LoggerInterface $logger, ApplicationPaths $paths): void
    {
        $containerBuilder->addDefinitions([
            ConfigManager::class => $config,
            ApplicationPaths::class => $paths,
            LoggerInterface::class => $logger,
            DatabaseInterface::class => !empty($config->getDatabaseConfig())
                ? \DI\create(Database::class)->constructor($config->getDatabaseConfig())
                : \DI\create(NullDatabase::class),
            PDO::class => fn(ContainerInterface $c) => $c->get(DatabaseInterface::class)->getPdo(),

            SecurityService::class => fn(ContainerInterface $c) =>
            new SecurityService($c->get(ConfigManager::class)->get('Security', [])),
        ]);

        $logger->info('Core services registered');
    }

    private static function registerAuth(ContainerBuilder $containerBuilder, ConfigManager $config, LoggerInterface $logger): void
    {
        $containerBuilder->addDefinitions([
            FileAuthStrategy::class => fn() => new FileAuthStrategy($config->get('user_file_path')),
            AuthStrategyInterface::class => function (ContainerInterface $c) use ($config) {
                if ($config->get('auth_strategy') === 'database') {
                    $database = $c->get(DatabaseInterface::class);
                    if ($database instanceof NullDatabase) {
                        throw new \RuntimeException('Cannot use database auth with NullDatabase.');
                    }
                    return $c->get(DatabaseAuthStrategy::class);
                }
                return $c->get(FileAuthStrategy::class);
            },
        ]);

        $logger->info('Auth strategy registered');
    }

    private static function registerCache(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->addDefinitions([
            CacheInterface::class => fn() => new Psr16Cache(new FilesystemAdapter()),
        ]);
    }

    private static function registerEntityManager(ContainerBuilder $containerBuilder, LoggerInterface $logger, ApplicationPaths $paths): void
    {
        $containerBuilder->addDefinitions([
            NullEntityManager::class => fn() => new NullEntityManager(),
            EntityManagerInterface::class => function (ContainerInterface $c) use ($paths) {
                $database = $c->get(DatabaseInterface::class);
                if ($database instanceof NullDatabase) {
                    return $c->get(NullEntityManager::class);
                }

                $isDevMode = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);

                $ormConfig = ORMSetup::createAttributeMetadataConfiguration([$paths->app('app/Entities')], $isDevMode);
                $proxyDir = $paths->app('app/Proxies');
                if (!is_dir($proxyDir)) {
                    mkdir($proxyDir, 0775, true);
                }
                $ormConfig->setProxyDir($proxyDir);
                $ormConfig->setProxyNamespace('App\\Proxies');

                return new EntityManager($database->getConnection(), $ormConfig);
            },
        ]);

        $logger->info('Entity manager registered');
    }

    private static function applyManualBindings(ContainerBuilder $containerBuilder, ConfigManager $config, LoggerInterface $logger): void
    {
        $bindings = $config->getDefinitions()['bindings'] ?? [];
        if ($bindings) {
            $containerBuilder->addDefinitions($bindings);
            $logger->info('Manual bindings applied', ['bindings' => array_keys($bindings)]);
        }
    }

    private static function registerProviders(ContainerBuilder $cb, ConfigManager $config, LoggerInterface $logger, ApplicationPaths $paths): void
    {
        $configuredProviders = $config->getDefinitions()['providers'] ?? [];
        $discovered = self::discoverClasses($paths->app('app/Providers'), 'App\\Providers', $logger, 'providers');

        $providers = array_unique([...$configuredProviders, ...$discovered]);

        foreach ($providers as $providerClass) {
            if (!class_exists($providerClass)) {
                $logger->warning('Configured provider class does not exist, skipping.', ['class' => $providerClass]);
                continue;
            }

            try {
                $provider = new $providerClass();
            } catch (\Throwable $e) {
                throw new \RuntimeException("Failed to instantiate provider {$providerClass}: {$e->getMessage()}", 0, $e);
            }

            if (!$provider instanceof ServiceProviderInterface) {
                throw new \RuntimeException("$providerClass must implement ServiceProviderInterface");
            }

            $provider->register($cb);
        }

        $logger->info('Service providers registered', ['providers' => $providers]);
    }

    private static function registerValidators(ContainerBuilder $cb, LoggerInterface $logger, ApplicationPaths $paths): void
    {
        $validators = self::discoverClasses($paths->app('app/Validators'), 'App\\Validators', $logger, 'validators');

        foreach ($validators as $class) {
            $cb->addDefinitions([
                $class => \DI\autowire($class),
                self::shortName($class) => \DI\get($class),
            ]);
        }

        $logger->info('Validators registered', ['validators' => $validators]);
    }

    /**
     * Recursively discover concrete, existing classes under a directory that map to a PSR-4 namespace.
     * Returns an empty array (with a warning logged) if the directory doesn't exist, rather than crashing.
     *
     * @return list<class-string>
     */
    private static function discoverClasses(string $dir, string $namespace, LoggerInterface $logger, string $label): array
    {
        if (!is_dir($dir)) {
            $logger->warning("Directory for {$label} not found, skipping auto-discovery.", ['path' => $dir]);
            return [];
        }

        $classes = [];

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
            );
        } catch (\UnexpectedValueException $e) {
            $logger->warning("Could not open {$label} directory, skipping auto-discovery.", [
                'path' => $dir,
                'error' => $e->getMessage(),
            ]);
            return [];
        }

        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($dir) + 1);
            $class = $namespace . '\\' . str_replace(['/', '\\', '.php'], ['\\', '\\', ''], $relative);

            if (class_exists($class)) {
                $classes[] = $class;
            } else {
                $logger->warning("File in {$label} directory did not resolve to an existing class.", [
                    'file' => $file->getPathname(),
                    'expected_class' => $class,
                ]);
            }
        }

        return $classes;
    }

    private static function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');
        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }
}