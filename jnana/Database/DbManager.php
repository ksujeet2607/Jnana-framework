<?php
namespace Jnana\Database;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Setup;
use Doctrine\ORM\EntityManager;
use Core\ApplicationPaths;
use Jnana\Security\AuthManager;
use Jnana\Session\SessionManager;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use Tracy\SessionStorage;

class DbManager
{
    private EntityManagerInterface $masterEm;
    private SessionManager $sessionManager;
    private ?CacheInterface $cache;
    /** cached company entity managers for request lifetime */
    private array $emCache = []; // ['company_42_fy_2024' => EntityManagerInterface]
    /** cached ORM config per entity-path key */
    private array $ormConfigCache = [];

    private AuthManager $authManager;

    public function __construct(
        EntityManagerInterface $masterEm,
        AuthManager $authManager,
        SessionManager $sessionManager,
        ?CacheInterface $cache = null
    ) {
        $this->masterEm  = $masterEm;
        $this->sessionManager  = $sessionManager;
        $this->authManager = $authManager;
        $this->cache     = $cache;
    }

    /**
     * Return Doctrine EntityManager for a company+fy.
     *
     * @param string|null $slug Optional name (used for cache key readability)
     * @param int         $companyId
     * @param int         $fyStartYear
     * @return EntityManagerInterface
     * @throws \RuntimeException
     * @throws DBALException
     */
    public function getCompanyEntityManager(?string $slug, int $companyId, int $fyStartYear): EntityManagerInterface
    {
        $cacheKey = $dbName = ($slug ?: "company") . "_{$companyId}_{$fyStartYear}";

        // Return cached and open EM if present
        if (isset($this->emCache[$cacheKey])) {
            $em = $this->emCache[$cacheKey];
            if ($em->isOpen()) {
                return $em;
            }
            unset($this->emCache[$cacheKey]);
        }

        // 1) Try to get db_info from PSR-16 cache
        $dbInfoCacheKey = "dbinfo_{$companyId}_{$fyStartYear}";
        $dbInfo = null;
        if ($this->cache) {
            try {
                $dbInfo = $this->cache->get($dbInfoCacheKey);
            } catch (InvalidArgumentException $e) {
                // ignore cache errors, fallback to DB
                $dbInfo = null;
            }
        }

        // 2) get DB user credentials based on role (optional)
        $currentUser = $this->authManager->getUser(); // AuthManager should return meaningful array/object
        $credentialRow = null;

        // resolve db user row if roles present (optional flow)
        if ($currentUser && !empty($currentUser['roles'])) {
            $roleCode = $currentUser['roles'][0] ?? null;
            if ($roleCode) {
                $connMaster = $this->masterEm->getConnection();
                $sql = "SELECT du.db_user, du.db_password
                        FROM roles r
                        JOIN db_users du ON du.id = r.db_user
                        WHERE r.code = ? LIMIT 1";
                $credentialRow = $connMaster->fetchAssociative($sql, [$roleCode]) ?: null;
            }
        }

        // 3) If dbInfo not cached, fetch from master DB
        if (!$dbInfo) {
            $connMaster = $this->masterEm->getConnection();

            // Try to get db info
            $sql = "SELECT db_name, db_port, db_host, db_driver, db_options
                    FROM db_info
                    WHERE company_id = ? AND fy_start_year = ? LIMIT 1";
            $row = $connMaster->fetchAssociative($sql, [$companyId, $fyStartYear]);
            if (!$row) {
                throw new \RuntimeException("DB info not found for db={$dbName} companyId={$companyId} fy={$fyStartYear}");
            }
            $dbInfo = $row;
            if ($this->cache) {
                try {
                    $this->cache->set($dbInfoCacheKey, $dbInfo, 300);
                } catch (InvalidArgumentException $e) {
                    // ignore cache set errors
                }
            }
        }

        // 4) Build connection params: prefer db_info values, then env defaults
        $driver     = $dbInfo['db_driver'] ?? getenv('DB_DRIVER') ?: 'pdo_mysql';
        $host       = $dbInfo['db_host'] ?? getenv('DB_HOST') ?: '127.0.0.1';
        $port       = $dbInfo['db_port'] ?? getenv('DB_PORT') ?: ($driver === 'pdo_pgsql' ? 5432 : 3306);
        $dbName     = $dbInfo['db_name'];
        $user       = $credentialRow['db_user'] ?? getenv('DB_USER') ?: 'app_user';
        $password   = $credentialRow['db_password'] ?? getenv('DB_PASS') ?: 'secret';

        //Save database name into session
        $this->sessionManager->set($this->sessionManager::DB_NAME, $dbName);

        // driver specific params (platform independent)
        $connectionParams = [
            'driver'   => $driver,
            'host'     => $host,
            'port'     => $port,
            'user'     => $user,
            'password' => $password,
            'dbname'   => $dbName,
            // charset key used for mysql; safe to include for both
            'charset'  => 'utf8mb4',
        ];

        // 5) Create/obtain Doctrine ORM config for company entities
        // Cache config per entity-path key for performance
        $entityPathsKey = $this->getCompanyEntityPathsKey();
        if (!isset($this->ormConfigCache[$entityPathsKey])) {
            // dev mode can be toggled via env
            $isDevMode = (bool)getenv('APP_DEBUG') ?: false;
            $companyEntityPaths = $this->getCompanyEntityPaths();
            $config = ORMSetup::createAttributeMetadataConfiguration($companyEntityPaths, $isDevMode);
            // set proxy settings (adjust directories/names as required)
            $proxyDir = $this->guessProxyDir();
            $proxyNs  = 'App\\Proxies';
            $config->setProxyDir($proxyDir);
            $config->setProxyNamespace($proxyNs);
            $config->setAutoGenerateProxyClasses($isDevMode);
            $this->ormConfigCache[$entityPathsKey] = $config;
        } else {
            $config = $this->ormConfigCache[$entityPathsKey];
        }

        // 6) Create DBAL connection and EntityManager
        $connection = DriverManager::getConnection($connectionParams);
        $companyEm = new EntityManager($connection, $config);

        // 7) cache for request lifetime
        $this->emCache[$cacheKey] = $companyEm;

        return $companyEm;
    }

    /**
     * Return the master EM.
     */
    public function getMasterEntityManager(): EntityManagerInterface
    {
        return $this->masterEm;
    }

    /**
     * Close and remove cached EMs (call at end of request)
     */
    public function closeAllCompanyEntityManagers(): void
    {
        foreach ($this->emCache as $k => $em) {
            try {
                if ($em->isOpen()) {
                    $em->close();
                }
            } catch (\Throwable $e) {
                // ignore closed exceptions
            }
            unset($this->emCache[$k]);
        }
    }

    /**
     * Helper: provide company entity paths (adjust as per your project).
     * Kept centralized so tests can override or DI can replace behavior.
     * @return string[]
     */
    protected function getCompanyEntityPaths(): array
    {
        // Company-specific entities belong to the consuming application, not this package.
        return [ApplicationPaths::instance()->app('app/Entities/Company')];
    }

    /**
     * A stable key representing the company entity paths for config caching.
     */
    protected function getCompanyEntityPathsKey(): string
    {
        return md5(implode(',', $this->getCompanyEntityPaths()));
    }

    /**
     * Guess a proxy dir under runtime/cache/proxies; adjust if you have a different layout.
     */
    protected function guessProxyDir(): string
    {
        // Doctrine-generated proxies are application runtime files.
        $dir = ApplicationPaths::instance()->app('app/Proxies');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}
