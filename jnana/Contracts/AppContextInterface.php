<?php

namespace Jnana\Contracts;

/**
 * Contract for the app-level "current request context" service
 * (typically resolves the authenticated user, tenant/company, etc).
 *
 * BaseController depends on this interface rather than a concrete
 * App\Services\... class, so the framework package stays free of
 * any dependency on application code.
 */
interface AppContextInterface
{
    public function user(): ?Authenticatable;
}
