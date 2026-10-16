# Security Phase 5 — Record Ownership and Tenant Isolation

Phase 5 adds `Jnana\Security\RecordAccessService`, a framework-level helper for record-level access decisions. It complements (and does not replace) route-level `#[Authorize]` checks.

## Why this exists

A route permission such as `invoices.view` means a user may use the invoice feature. It does not mean that user may view invoice `123` from another user or company. Every read and mutation must be scoped to the authenticated user's owner/tenant boundary.

## Available assertions

```php
use Jnana\Security\RecordAccessService;

// Only the record owner can access it.
$recordAccess->assertOwner($invoice, 'owner_id', 'id');

// Record and current user must have the same tenant/company identifier.
$recordAccess->assertSameTenant($invoice, 'company_id', 'company_id');

// Owner or a user with records.view_tenant, but only inside the same tenant.
$recordAccess->assertOwnerOrTenantPermission(
    $invoice,
    'invoices.view_tenant',
    'owner_id',
    'company_id',
    'id',
    'company_id'
);
```

The default fields are `owner_id`, `tenant_id`, and authenticated user `id`/`tenant_id`. For objects, the helper checks a conventional getter such as `getOwnerId()` before a public property. Missing identifiers fail closed. Unauthenticated access raises `UnauthorizedException`; policy failures raise `ForbiddenException`.

## Important database rule

The helper makes an authorization decision about a record already loaded. It does not replace tenant-scoped queries. Prefer querying by both record ID and tenant ID:

```php
$invoice = $repository->findOneBy([
    'id' => $invoiceId,
    'company' => $currentCompany,
]);

if ($invoice === null) {
    // Return not found; do not disclose another tenant's record.
    throw new \Jnana\Exceptions\RouteNotFoundException('Invoice not found.');
}

$recordAccess->assertSameTenant($invoice, 'company_id', 'company_id');
```

Adapt field names and repository associations to your actual entity mapping. Do not copy the example query literally if `company` is an association.

## Rules for reads and writes

- Apply route-level authentication and permission checks first.
- Scope repository queries by tenant whenever applicable; never fetch globally by an untrusted ID and assume route permission is enough.
- Check record ownership/tenant before displaying, editing, deleting, downloading, or exporting the record.
- For updates/deletes, use a transaction and re-check the relevant boundary at mutation time if records can change concurrently.
- Do not add an implicit admin bypass. Cross-tenant access, if genuinely needed, must be a separately designed, audited, narrowly scoped capability.
- Treat tenant/company IDs from request parameters as untrusted. Resolve the current tenant from trusted authenticated context and verify membership.

`RecordAccessService` is intentionally ORM-agnostic. Application code remains responsible for loading records safely and choosing the appropriate policy for each operation.
