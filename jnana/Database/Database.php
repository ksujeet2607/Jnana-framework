<?php
namespace Jnana\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use PDO;

class Database implements DatabaseInterface
{
    private Connection $conn;
    private PDO $pdo;

    /**
     * Database constructor.
     * Initializes the connection with the provided configuration.
     *
     * @param array $config
     * @throws \Doctrine\DBAL\Exception
     */
    public function __construct(array $config)
    {
        $this->connect($config);
    }

    /**
     * Establishes a database connection.
     *
     * @param array $config
     * @throws \Doctrine\DBAL\Exception
     */
    public function connect(array $config): void
    {
        $this->conn = DriverManager::getConnection($config);
        $this->pdo = $this->connectPdo($config);
    }

    private function connectPdo(array $config): PDO
    {
        $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
        $username = $config['user'];
        $password = $config['password'];

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Returns the database connection.
     *
     * @return \Doctrine\DBAL\Connection
     */
    public function getConnection(): Connection
    {
        return $this->conn;
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function createQueryBuilder(): QueryBuilder
    {
        return $this->conn->createQueryBuilder();
    }
}
