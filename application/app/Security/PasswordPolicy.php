<?php

declare(strict_types=1);

namespace Barama\Security;

final class PasswordPolicy
{
    /** @return array<int,string> */
    public static function errors(string $password, ?string $confirmation = null): array
    {
        $minimum = max(12, (int) config('auth.password_min_length', 12));
        $errors = [];

        if (strlen($password) < $minimum) {
            $errors[] = "Use at least {$minimum} characters.";
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            $errors[] = 'Include at least one uppercase letter.';
        }

        if (preg_match('/[a-z]/', $password) !== 1) {
            $errors[] = 'Include at least one lowercase letter.';
        }

        if (preg_match('/\d/', $password) !== 1) {
            $errors[] = 'Include at least one number.';
        }

        if (preg_match('/[^A-Za-z0-9]/', $password) !== 1) {
            $errors[] = 'Include at least one symbol.';
        }

        if ($confirmation !== null && $password !== $confirmation) {
            $errors[] = 'The password confirmation does not match.';
        }

        return $errors;
    }

    public static function valid(string $password): bool
    {
        return self::errors($password) === [];
    }

    private function __construct()
    {
    }
}
