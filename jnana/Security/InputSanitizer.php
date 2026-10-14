<?php

namespace Jnana\Security;

use http\Exception\InvalidArgumentException;

class InputSanitizer
{
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeArray(array $input): array
    {
        return array_map([self::class, 'sanitizeString'], $input);
    }

    public static function validateInt($input): int
    {
        if(!filter_var($input, FILTER_VALIDATE_INT)) {
            throw new InvalidArgumentException("Invalid integer input.");
        }
        return (int) $input;
    }

}