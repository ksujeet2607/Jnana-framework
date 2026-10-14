<?php

namespace Jnana\Database;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Internal\Hydration\AbstractHydrator;
use Doctrine\ORM\Mapping;
use Doctrine\ORM\Proxy\ProxyFactory;
use Doctrine\ORM\Query\Expr;

class NullEntityManager implements EntityManagerInterface
{
    public function __call(string $name, array $arguments)
    {
        throw new \RuntimeException("EntityManager is unavailable because NullDatabase is in use.");
    }

    private function unavailable()
    {
        throw new \RuntimeException("EntityManager is unavailable because NullDatabase is in use.");
    }

    public function __invoke(): void
    {
        $this->unavailable();
    }

    // Implement required methods of EntityManagerInterface
    public function getRepository($className): \Doctrine\ORM\EntityRepository
    {
        $this->unavailable();
    }

    public function getCache(): ?\Doctrine\ORM\Cache
    {
        $this->unavailable();
    }

    public function getConnection(): \Doctrine\DBAL\Connection
    {
        $this->unavailable();
    }

    public function close(): void
    {
        $this->unavailable();
    }

    public function persist($entity): void
    {
        $this->unavailable();
    }

    public function remove($entity): void
    {
        $this->unavailable();
    }

    public function flush(): void
    {
        $this->unavailable();
    }

    public function find($className, $id, $lockMode = null, $lockVersion = null): ?object
    {
        $this->unavailable();
    }

    public function getUnitOfWork(): \Doctrine\ORM\UnitOfWork
    {
        $this->unavailable();
    }

    public function getMetadataFactory(): Mapping\ClassMetadataFactory
    {
        $this->unavailable();
    }

    public function initializeObject($obj): void
    {
        $this->unavailable();
    }

    public function contains($entity): bool
    {
        $this->unavailable();
    }

    public function lock($entity, $lockMode, $lockVersion = null): void
    {
        $this->unavailable();
    }

    public function merge($entity)
    {
        $this->unavailable();
    }

    public function refresh(object $entity, int|null|\Doctrine\DBAL\LockMode $lockMode = null): void
    {
        $this->unavailable();
    }

    public function detach($entity): void
    {
        $this->unavailable();
    }

    public function clear($entityName = null): void
    {
        $this->unavailable();
    }

    public function getReference($entityName, $id): ?object
    {
        $this->unavailable();
    }

    public function getPartialReference($entityName, $identifier)
    {
        $this->unavailable();
    }

    public function createQuery($dql = ''): \Doctrine\ORM\Query
    {
        $this->unavailable();
    }

    public function createNamedQuery($name)
    {
        $this->unavailable();
    }

    public function createNativeQuery($sql, $rsm): \Doctrine\ORM\NativeQuery
    {
        $this->unavailable();
    }

    public function createQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        $this->unavailable();
    }

    public function getConfiguration(): \Doctrine\ORM\Configuration
    {
        $this->unavailable();
    }

    public function getFilters(): \Doctrine\ORM\Query\FilterCollection
    {
        $this->unavailable();
    }

    public function isFiltersStateClean(): bool
    {
        $this->unavailable();
    }

    public function hasFilters(): bool
    {
        $this->unavailable();
    }

    public function getExpressionBuilder(): Expr
    {
        $this->unavailable();
    }

    public function beginTransaction(): void
    {
        $this->unavailable();
    }

    public function wrapInTransaction(callable $func): mixed
    {
        $this->unavailable();
    }

    public function commit(): void
    {
        $this->unavailable();
    }

    public function rollback(): void
    {
        $this->unavailable();
    }

    public function getEventManager(): EventManager
    {
        $this->unavailable();
    }

    public function isOpen(): bool
    {
        $this->unavailable();
    }

    public function newHydrator(int|string $hydrationMode): AbstractHydrator
    {
        $this->unavailable();
    }

    public function getProxyFactory(): ProxyFactory
    {
        $this->unavailable();
    }

    public function getClassMetadata(string $className): Mapping\ClassMetadata
    {
        $this->unavailable();
    }

    public function isUninitializedObject(mixed $value): bool
    {
        $this->unavailable();
    }
}
