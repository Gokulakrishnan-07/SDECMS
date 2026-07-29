<?php

/**
 * Global helper functions.
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Session;

if (!function_exists('config')) {
    /**
     * Dot-notation access to configuration, e.g. config('db.host').
     */
    function config(string $key, mixed $default = null): mixed
    {
        $value = $GLOBALS['config'];
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }
}

if (!function_exists('base_url')) {
    /**
     * Absolute URL path for a route, respecting the configured base path.
     */
    function base_url(string $path = ''): string
    {
        return rtrim(config('app.base_url', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('e')) {
    /**
     * HTML-escape output (XSS protection). Always use in views.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . base_url($path));
        exit;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return App\Core\CSRF::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('flash')) {
    /**
     * Set or get a one-shot flash message.
     */
    function flash(string $key, ?string $message = null): ?string
    {
        if ($message !== null) {
            Session::put('_flash_' . $key, $message);
            return null;
        }
        $value = Session::get('_flash_' . $key);
        Session::forget('_flash_' . $key);
        return $value;
    }
}

if (!function_exists('money')) {
    /**
     * Format an amount in Indian numbering style with rupee symbol.
     */
    function money(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);
        $formatted = number_format($amount, 2, '.', '');
        [$int, $dec] = explode('.', $formatted);
        $sign = '';
        if (str_starts_with($int, '-')) {
            $sign = '-';
            $int = substr($int, 1);
        }
        if (strlen($int) > 3) {
            $last3 = substr($int, -3);
            $rest  = substr($int, 0, -3);
            $rest  = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $int   = $rest . ',' . $last3;
        }
        return $sign . '₹' . $int . '.' . $dec;
    }
}

if (!function_exists('role_label')) {
    function role_label(string $role): string
    {
        return match ($role) {
            'administrator'   => 'Administrator',
            'principal'       => 'Principal',
            'accounts'        => 'Accounts Department',
            'department_head' => 'Department Head',
            'viewer'          => 'Viewer',
            default           => ucfirst($role),
        };
    }
}

if (!function_exists('amount_in_words')) {
    /**
     * Convert an amount to English words using the Indian numbering system
     * (Thousand / Lakh / Crore). No "Only" suffix, per spec.
     * e.g. 250000 => "Two Lakh Fifty Thousand Rupees"
     */
    function amount_in_words(float|int|string|null $amount): string
    {
        $amount = (float) ($amount ?? 0);
        if ($amount <= 0) {
            return '';
        }

        $rupees = (int) floor($amount);
        $paise  = (int) round(($amount - $rupees) * 100);

        $words = trim(indian_number_words($rupees));
        $out   = $words === '' ? '' : $words . ' Rupees';

        if ($paise > 0) {
            $p = trim(indian_number_words($paise));
            $out = ($out !== '' ? $out . ' and ' : '') . $p . ' Paise';
        }
        return $out;
    }
}

if (!function_exists('indian_number_words')) {
    /**
     * Integer → words, Indian system. Helper for amount_in_words().
     */
    function indian_number_words(int $n): string
    {
        if ($n === 0) {
            return 'Zero';
        }

        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
            'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
            'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        $twoDigits = static function (int $num) use ($ones, $tens): string {
            if ($num < 20) {
                return $ones[$num];
            }
            return trim($tens[intdiv($num, 10)] . ' ' . $ones[$num % 10]);
        };

        $threeDigits = static function (int $num) use ($ones, $twoDigits): string {
            $h = intdiv($num, 100);
            $rest = $num % 100;
            $parts = [];
            if ($h > 0) {
                $parts[] = $ones[$h] . ' Hundred';
            }
            if ($rest > 0) {
                $parts[] = $twoDigits($rest);
            }
            return implode(' ', $parts);
        };

        $crore = intdiv($n, 10000000);
        $n %= 10000000;
        $lakh = intdiv($n, 100000);
        $n %= 100000;
        $thousand = intdiv($n, 1000);
        $n %= 1000;
        $hundreds = $n;

        $parts = [];
        if ($crore > 0) {
            $parts[] = indian_number_words($crore) . ' Crore';
        }
        if ($lakh > 0) {
            $parts[] = $twoDigits($lakh) . ' Lakh';
        }
        if ($thousand > 0) {
            $parts[] = $twoDigits($thousand) . ' Thousand';
        }
        if ($hundreds > 0) {
            $parts[] = $threeDigits($hundreds);
        }
        return implode(' ', $parts);
    }
}
