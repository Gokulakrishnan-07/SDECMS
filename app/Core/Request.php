<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight request wrapper. Handles JSON bodies, query params and files.
 */
class Request
{
    private static ?array $jsonCache = null;

    public static function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        // Method spoofing for HTML forms: <input name="_method" value="PUT">
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoofed = strtoupper((string) $_POST['_method']);
            if (in_array($spoofed, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $spoofed;
            }
        }
        return $method;
    }

    /**
     * Request path relative to the application base URL, e.g. "/api/budgets".
     */
    public static function path(): string
    {
        $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $base = rtrim(config('app.base_url', ''), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        // Also strip /index.php if present
        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }
        return '/' . trim($uri, '/');
    }

    public static function isAjax(): bool
    {
        return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with(self::path(), '/api/');
    }

    /**
     * Decoded JSON request body (cached).
     */
    public static function json(): array
    {
        if (self::$jsonCache === null) {
            $raw = file_get_contents('php://input') ?: '';
            $decoded = json_decode($raw, true);
            self::$jsonCache = is_array($decoded) ? $decoded : [];
        }
        return self::$jsonCache;
    }

    /**
     * Merged input: JSON body > POST > GET. Values are NOT html-escaped here;
     * escaping happens on output (views) — validation happens per-field.
     */
    public static function input(string $key, mixed $default = null): mixed
    {
        $json = self::json();
        return $json[$key] ?? $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function all(): array
    {
        return array_merge($_GET, $_POST, self::json());
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function file(string $key): ?array
    {
        $file = $_FILES[$key] ?? null;
        return ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $file : null;
    }

    public static function ip(): string
    {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public static function userAgent(): string
    {
        return substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    }
}
