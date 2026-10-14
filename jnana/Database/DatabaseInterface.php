<?php

namespace Jnana\Database;

use Doctrine\DBAL\Connection;

interface DatabaseInterface
{
    public function connect(array $dbConfig): void;

    public function getConnection(): ?Connection;
    public function getPdo(): ?\PDO;

}