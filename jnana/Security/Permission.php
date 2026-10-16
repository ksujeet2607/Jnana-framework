<?php

declare(strict_types=1);

namespace Jnana\Security;

use Attribute;

/** Declares one or more required permissions on a controller or action. */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Permission
{
    /**
     * @param string|list<string> $permissions
     */
    public function __construct(
        public string|array $permissions,
        public bool $requireAll = true
    ) {
        $permissions = is_string($permissions) ? [$permissions] : $permissions;
        if ($permissions === []) {
            throw new \InvalidArgumentException('At least one permission is required.');
        }
        foreach ($permissions as $permission) {
            if (!is_string($permission) || trim($permission) === '') {
                throw new \InvalidArgumentException('Permission names must be non-empty strings.');
            }
        }
    }

    public function names(): array
    {
        return is_string($this->permissions)
            ? [$this->permissions]
            : $this->permissions;
    }
}
