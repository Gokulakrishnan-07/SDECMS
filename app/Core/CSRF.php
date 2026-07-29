<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token generation and verification.
 * Tokens are accepted from the `_token` form field or `X-CSRF-Token` header.
 */
class CSRF
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (!Session::get(self::KEY)) {
            Session::put(self::KEY, bin2hex(random_bytes(32)));
        }
        return (string) Session::get(self::KEY);
    }

    public static function verify(?string $token): bool
    {
        $stored = Session::get(self::KEY);
        return is_string($stored) && is_string($token) && hash_equals($stored, $token);
    }

    public static function tokenFromRequest(): ?string
    {
        return $_POST['_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? (Request::json()['_token'] ?? null);
    }
}
