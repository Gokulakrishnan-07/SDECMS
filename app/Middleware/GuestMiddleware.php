<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;

/**
 * Redirects already-authenticated users away from guest pages (login).
 */
class GuestMiddleware
{
    public static function handle(): void
    {
        if (Auth::check()) {
            redirect('dashboard');
        }
    }
}
