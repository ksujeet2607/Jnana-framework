# Security Phase 6 — Database-Level Access Enforcement

## New/updated services

- `Jnana\Security\TenantContext`: resolves `tenant_id` (or legacy `company_id`) from trusted authenticated state. CLI/background work must use `forBackgroundJob($tenantId, $jobName)`.
- `Jnana\Security\DoctrineRecordAccessService`: Doctrine ORM 3-compatible tenant-scoped read/list/create/update/delete/export operations.
- `Jnana\Security\RecordAccessService`: existing owner and tenant policy assertions remain the record-level policy layer.

## Required application rules

1. Use `findForCurrentTenant()` instead of `EntityManager::find()` for tenant-owned records.
2. Use `findAllForCurrentTenant()` / `exportForCurrentTenant()` for list and export paths.
3. Use `createForCurrentTenant()` with a writable-field allow-list. Never include owner or tenant properties in that list.
4. Use `updateForCurrentTenant()` and `deleteForCurrentTenant()` rather than mutating an entity loaded without tenant scope.
5. Use the scoped lookup before file downloads; never trust a request-provided file path.
6. Tenant IDs and owner IDs come from `TenantContext`, never from HTTP payloads.
7. Missing tenant context throws `CompanyContextRequiredException`. Do not catch it and continue with an unscoped query.
8. No implicit admin bypass exists. Tenant-wide administrators must be explicitly authorized, and their query must still include the tenant predicate.
9. Background jobs must be created by trusted server-side code with a fixed tenant ID and descriptive job name. Creates require an explicit trusted owner ID. Do not derive either from user input.

## Field configuration

The service expects Doctrine mapped scalar property names, not SQL column names. For a mapping like `#[ORM\Column(name: 'tenant_id')] private int $tenantId`, pass `tenantId`, not `tenant_id`.

The current implementation requires a single-field entity identifier and public setters for allow-listed fields. Entities with composite IDs, tenant associations, or association-based owners should use explicit repository adapters rather than weakening this boundary.

## Failure behavior

Missing/cross-tenant IDs return the same not-found exception from scoped lookups to reduce record enumeration. Invalid mapping configuration throws an exception rather than falling back to unscoped access. Owner checks remain strict when an owner field is provided.
