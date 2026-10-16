<?php

declare(strict_types=1);

namespace Jnana\Security;

use Jnana\Exceptions\ForbiddenException;
use Jnana\Exceptions\UnauthorizedException;

/**
 * Record-level authorization helpers. Route permissions do not replace these checks.
 * This service deliberately does not query a database: applications should perform
 * tenant-scoped queries and then use these assertions before acting on a record.
 */
final class RecordAccessService
{
    public function __construct(
        private readonly AuthManager $auth,
        private readonly AuthorizationService $authorization,
    ) {
    }

    /** Deny unless the authenticated user owns the record. */
    public function assertOwner(array|object $record, string $ownerField = 'owner_id', string $userIdField = 'id'): void
    {
        $user = $this->requireUser();
        $ownerId = $this->read($record, $ownerField);
        $userId = $user[$userIdField] ?? null;

        if (!$this->sameIdentifier($ownerId, $userId)) {
            throw new ForbiddenException('You are not authorized to access this record.');
        }
    }

    /** Deny unless both the record and authenticated user have the same non-empty tenant identifier. */
    public function assertSameTenant(array|object $record, string $tenantField = 'tenant_id', string $userTenantField = 'tenant_id'): void
    {
        $user = $this->requireUser();
        $recordTenant = $this->read($record, $tenantField);
        $userTenant = $user[$userTenantField] ?? null;

        if (!$this->sameIdentifier($recordTenant, $userTenant)) {
            throw new ForbiddenException('You are not authorized to access this tenant resource.');
        }
    }

    /**
     * Require tenant equality, then allow the owner or a user with the explicit
     * tenant-wide permission. There is intentionally no implicit admin bypass.
     */
    public function assertOwnerOrTenantPermission(
        array|object $record,
        string $permission = 'records.view_tenant',
        string $ownerField = 'owner_id',
        string $tenantField = 'tenant_id',
        string $userIdField = 'id',
        string $userTenantField = 'tenant_id',
    ): void {
        $this->assertSameTenant($record, $tenantField, $userTenantField);

        try {
            $this->assertOwner($record, $ownerField, $userIdField);
            return;
        } catch (ForbiddenException) {
            // A tenant-wide permission can grant access to another owner's record,
            // but never to a record in a different tenant.
        }

        if (!$this->authorization->can($permission)) {
            throw new ForbiddenException('You are not authorized to access this record.');
        }
    }

    private function requireUser(): array
    {
        $user = $this->auth->getUser();
        if ($user === null) {
            throw new UnauthorizedException('Authentication is required to access this resource.');
        }

        return $user;
    }

    private function read(array|object $source, string $field): mixed
    {
        if (is_array($source)) {
            return $source[$field] ?? null;
        }

        $getter = 'get' . str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $field)));
        if (method_exists($source, $getter)) {
            return $source->{$getter}();
        }

        return isset($source->{$field}) ? $source->{$field} : null;
    }

    private function sameIdentifier(mixed $left, mixed $right): bool
    {
        if (!is_scalar($left) || !is_scalar($right) || (string) $left === '' || (string) $right === '') {
            return false;
        }

        return (string) $left === (string) $right;
    }
}
