<?php

declare(strict_types=1);

namespace Jnana\Security;

use Jnana\Exceptions\CompanyContextRequiredException;

/**
 * Resolves the active tenant from trusted server-side authentication data.
 *
 * Do not populate this service from request parameters. Background workers must
 * construct an explicit job context with forBackgroundJob().
 */
final class TenantContext
{
    private function __construct(
        private readonly ?AuthManager $auth,
        private readonly ?int $backgroundTenantId = null,
        private readonly ?string $backgroundJob = null,
    ) {
    }

    public static function fromAuthentication(AuthManager $auth): self
    {
        return new self($auth);
    }

    /**
     * Explicit tenant scope for a trusted server-side job entry point.
     * Never pass HTTP request values to this factory.
     */
    public static function forBackgroundJob(int $tenantId, string $jobName): self
    {
        if ($tenantId < 1 || trim($jobName) === '') {
            throw new \InvalidArgumentException('A positive tenant ID and named background job are required.');
        }

        return new self(null, $tenantId, $jobName);
    }

    public function tenantId(): int
    {
        if ($this->backgroundTenantId !== null) {
            return $this->backgroundTenantId;
        }

        $user = $this->auth?->getUser();
        $tenantId = $user['tenant_id'] ?? $user['company_id'] ?? null;

        if (!is_int($tenantId) && !(is_string($tenantId) && ctype_digit($tenantId))) {
            throw new CompanyContextRequiredException('A trusted tenant context is required.');
        }

        $tenantId = (int) $tenantId;
        if ($tenantId < 1) {
            throw new CompanyContextRequiredException('A trusted tenant context is required.');
        }

        return $tenantId;
    }

    /** Trusted authentication payload used only to select the matching tenant key. */
    public function authenticatedUser(): array
    {
        if ($this->isBackgroundJob()) {
            return [];
        }

        $user = $this->auth?->getUser();
        if ($user === null) {
            // tenantId() will throw the framework's missing-context exception.
            $this->tenantId();
            return [];
        }

        return $user;
    }

    public function actorId(): ?int
    {
        if ($this->backgroundTenantId !== null) {
            return null;
        }

        $id = $this->auth?->getUser()['id'] ?? null;
        return (is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0
            ? (int) $id
            : null;
    }

    public function isBackgroundJob(): bool
    {
        return $this->backgroundTenantId !== null;
    }

    public function backgroundJobName(): ?string
    {
        return $this->backgroundJob;
    }
}
