<?php

namespace Jnana\Security;

final class PasswordHasher
{
    /**
     * Hash a plain password
     */
    public static function hash(string $plainPassword): string
    {
        return password_hash(
            $plainPassword,
            PASSWORD_DEFAULT,
            [
                'cost' => 12, // good balance between security & performance
            ]
        );
    }

    /**
     * Verify plain password against hash
     */
    public static function verify(string $plainPassword, string $hash): bool
    {
        return password_verify($plainPassword, $hash);
    }

    /**
     * Check if hash needs rehash (future-proofing)
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash(
            $hash,
            PASSWORD_DEFAULT,
            [
                'cost' => 12,
            ]
        );
    }
}
