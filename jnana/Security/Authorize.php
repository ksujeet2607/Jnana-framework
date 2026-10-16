<?php

declare(strict_types=1);

namespace Jnana\Security;

use Attribute;

/**
 * Declares authentication, role, and permission requirements for a controller
 * or controller action. Class-level and method-level requirements are additive.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Authorize
{
    /**
     * @param list<string> $roles The user must have at least one listed role.
     * @param list<string> $permissions Required permissions; all or any are checked according to $requireAllPermissions.
     */
    public function __construct(
        public array $roles = [],
        public array $permissions = [],
        public bool $requireAllPermissions = true
    ) {
        self::validateNames($roles, 'role');
        self::validateNames($permissions, 'permission');
    }

    private static function validateNames(array $names, string $type): void
    {
        foreach ($names as $name) {
            if (!is_string($name) || trim($name) === '') {
                throw new \InvalidArgumentException("Authorize {$type} names must be non-empty strings.");
            }
        }
    }
}
