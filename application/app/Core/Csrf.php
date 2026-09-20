<?php

declare(strict_types=1);

namespace Barama\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        $token = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_string($token) || strlen($token) < 32) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::SESSION_KEY] = $token;
        }

        return $token;
    }

    public static function validate(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        return hash_equals(self::token(), $token);
    }

    public static function regenerate(): string
    {
        unset($_SESSION[self::SESSION_KEY]);
        return self::token();
    }

    private function __construct()
    {
    }
}
