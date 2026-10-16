<?php

declare(strict_types=1);

namespace Jnana\Security;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Jnana\Exceptions\ForbiddenException;
use Jnana\Exceptions\RouteNotFoundException;

/**
 * Tenant-scoped Doctrine operations.
 *
 * The configured tenant/owner names are Doctrine mapped FIELD names (for
 * example tenantId), not database column names (for example tenant_id).
 * The service intentionally supports scalar mapped fields only; applications
 * using tenant associations should implement an explicit repository adapter.
 */
final class DoctrineRecordAccessService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
        private readonly RecordAccessService $recordAccess,
    ) {
    }

    public function findForCurrentTenant(
        string $entityClass,
        int|string $id,
        string $tenantField = 'tenantId',
        ?string $ownerField = null,
    ): object {
        $tenantId = $this->tenantContext->tenantId();
        $metadata = $this->metadata($entityClass);
        $this->assertScalarField($metadata, $tenantField);
        if ($ownerField !== null) {
            $this->assertScalarField($metadata, $ownerField);
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('record')
            ->from($entityClass, 'record')
            ->where('record.' . $metadata->getSingleIdentifierFieldName() . ' = :recordId')
            ->andWhere('record.' . $tenantField . ' = :tenantId')
            ->setParameter('recordId', $id)
            ->setParameter('tenantId', $tenantId)
            ->setMaxResults(1);

        if ($ownerField !== null) {
            $actorId = $this->tenantContext->actorId();
            if ($actorId === null) {
                throw new ForbiddenException('An authenticated actor is required for owner-scoped access.');
            }
            $qb->andWhere('record.' . $ownerField . ' = :ownerId')->setParameter('ownerId', $actorId);
        }

        $record = $qb->getQuery()->getOneOrNullResult();
        if ($record === null) {
            // Same response for missing IDs and cross-tenant IDs prevents enumeration.
            throw new RouteNotFoundException('Record not found.');
        }

        if (!$this->tenantContext->isBackgroundJob()) {
            $authUser = $this->tenantContext->authenticatedUser();
            $userTenantField = array_key_exists('tenant_id', $authUser) ? 'tenant_id' : 'company_id';
            $this->recordAccess->assertSameTenant($record, $tenantField, $userTenantField);
        }
        if ($ownerField !== null) {
            $this->recordAccess->assertOwner($record, $ownerField, 'id');
        }

        return $record;
    }

    /** Return only rows inside the current tenant boundary. */
    public function findAllForCurrentTenant(
        string $entityClass,
        string $tenantField = 'tenantId',
        array $orderBy = [],
    ): array {
        $tenantId = $this->tenantContext->tenantId();
        $metadata = $this->metadata($entityClass);
        $this->assertScalarField($metadata, $tenantField);

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('record')
            ->from($entityClass, 'record')
            ->where('record.' . $tenantField . ' = :tenantId')
            ->setParameter('tenantId', $tenantId);

        foreach ($orderBy as $field => $direction) {
            $this->assertScalarField($metadata, (string) $field);
            $direction = strtoupper((string) $direction);
            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new \InvalidArgumentException('Order direction must be ASC or DESC.');
            }
            $qb->addOrderBy('record.' . $field, $direction);
        }

        $records = $qb->getQuery()->getResult();
        if (!$this->tenantContext->isBackgroundJob()) {
            $authUser = $this->tenantContext->authenticatedUser();
            $userTenantField = array_key_exists('tenant_id', $authUser) ? 'tenant_id' : 'company_id';
            foreach ($records as $record) {
                $this->recordAccess->assertSameTenant($record, $tenantField, $userTenantField);
            }
        }
        return $records;
    }

    /**
     * Create using an explicit allow-list. owner/tenant values always come from
     * trusted context, never from the submitted payload.
     *
     * @param list<string> $writableFields Doctrine property names allowed for this operation
     */
    public function createForCurrentTenant(
        object $entity,
        array $payload,
        array $writableFields,
        string $tenantField = 'tenantId',
        string $ownerField = 'ownerId',
        ?int $backgroundOwnerId = null,
    ): object {
        $metadata = $this->metadata($entity::class);
        $this->assertScalarField($metadata, $tenantField);
        $this->assertScalarField($metadata, $ownerField);

        $this->assertNoProtectedPayloadFields($payload, array_unique([$tenantField, $ownerField, 'tenant_id', 'owner_id', 'tenantId', 'ownerId']));
        $this->applyPayload($entity, $payload, $writableFields);

        $tenantSetter = $this->setter($tenantField);
        $entity->{$tenantSetter}($this->tenantContext->tenantId());

        $actorId = $this->tenantContext->actorId();
        if ($actorId === null) {
            if (!$this->tenantContext->isBackgroundJob() || $backgroundOwnerId === null || $backgroundOwnerId < 1) {
                throw new ForbiddenException('Background-job creates require an explicit trusted owner ID.');
            }
            $actorId = $backgroundOwnerId;
        }
        $entity->{$this->setter($ownerField)}($actorId);

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        if (!$this->tenantContext->isBackgroundJob()) {
            $this->recordAccess->assertSameTenant($entity, $tenantField, 'tenant_id');
            $this->recordAccess->assertOwner($entity, $ownerField, 'id');
        }
        return $entity;
    }

    /**
     * Loads a tenant-scoped record and mutates only explicitly writable fields.
     * Protected ownership/tenant fields cannot be mass-assigned.
     *
     * @param list<string> $writableFields
     */
    public function updateForCurrentTenant(
        string $entityClass,
        int|string $id,
        array $payload,
        array $writableFields,
        string $tenantField = 'tenantId',
        ?string $ownerField = null,
    ): object {
        $record = $this->findForCurrentTenant($entityClass, $id, $tenantField, $ownerField);
        $this->assertNoProtectedPayloadFields($payload, array_unique([$tenantField, $ownerField ?? '', 'tenant_id', 'owner_id', 'tenantId', 'ownerId']));
        $this->applyPayload($record, $payload, $writableFields);
        $this->entityManager->flush();
        return $record;
    }

    public function deleteForCurrentTenant(
        string $entityClass,
        int|string $id,
        string $tenantField = 'tenantId',
        ?string $ownerField = null,
    ): void {
        $record = $this->findForCurrentTenant($entityClass, $id, $tenantField, $ownerField);
        $this->entityManager->remove($record);
        $this->entityManager->flush();
    }

    /**
     * Export data through the same tenant-scoped query path as ordinary listing.
     * Callers should serialize only the fields required for the export.
     *
     * @return list<object>
     */
    public function exportForCurrentTenant(
        string $entityClass,
        string $tenantField = 'tenantId',
        array $orderBy = [],
    ): array {
        return $this->findAllForCurrentTenant($entityClass, $tenantField, $orderBy);
    }

    private function metadata(string $entityClass): ClassMetadata
    {
        if (!class_exists($entityClass)) {
            throw new \InvalidArgumentException('Unknown entity class.');
        }
        $metadata = $this->entityManager->getClassMetadata($entityClass);
        if (count($metadata->getIdentifierFieldNames()) !== 1) {
            throw new \InvalidArgumentException('Tenant-scoped access currently requires a single-field entity identifier.');
        }
        return $metadata;
    }

    private function assertScalarField(ClassMetadata $metadata, string $field): void
    {
        if ($field === '' || !$metadata->hasField($field)) {
            throw new \InvalidArgumentException(sprintf(
                'Field "%s" is not a mapped scalar field on %s.',
                $field,
                $metadata->getName()
            ));
        }
    }

    private function assertNoProtectedPayloadFields(array $payload, array $protectedFields): void
    {
        foreach ($protectedFields as $field) {
            if (array_key_exists($field, $payload)) {
                throw new ForbiddenException(sprintf('Field "%s" is server-managed and cannot be submitted.', $field));
            }
        }
    }

    private function applyPayload(object $entity, array $payload, array $writableFields): void
    {
        $allowed = array_fill_keys($writableFields, true);
        foreach ($payload as $field => $value) {
            if (!is_string($field) || !isset($allowed[$field])) {
                throw new ForbiddenException(sprintf('Field "%s" is not writable through this operation.', (string) $field));
            }
            $setter = $this->setter($field);
            if (!is_callable([$entity, $setter])) {
                throw new \InvalidArgumentException(sprintf('Writable field "%s" has no public setter.', $field));
            }
            $entity->{$setter}($value);
        }
    }

    private function setter(string $field): string
    {
        return 'set' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $field)));
    }
}
