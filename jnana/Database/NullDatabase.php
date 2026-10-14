<?php

namespace Jnana\Database;

use Doctrine\DBAL\Connection;

class NullDatabase implements DatabaseInterface
{
    public function connect(?array $dbConfig): void
    {
    }

    /**
     * @return null
     */
    public function getConnection(): Connection|null
    {
        throw new \RuntimeException("Database connection is not configured");
    }
    public function getPdo(): ?\PDO
    {
        return null;
    }
}