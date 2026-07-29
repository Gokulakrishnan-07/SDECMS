<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Requires an authenticated, active user. AJAX gets 401 JSON, web redirects.
 */
class AuthMiddleware
{
    public static function handle(): void
    {
        if (Auth::check() && Auth::user() !== null) {
            return;
        }

        if (Request::isAjax()) {
            Response::error('Unauthenticated. Please log in.', 401);
        }

        if (Session::get('_timed_out')) {
            Session::forget('_timed_out');
            flash('error', 'Your session expired due to inactivity. Please log in again.');
        }

        redirect('login');
    }
}
