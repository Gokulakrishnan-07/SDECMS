<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\CSRF;
use App\Core\Request;
use App\Core\Response;

/**
 * Verifies the CSRF token on every state-changing request (POST/PUT/DELETE).
 */
class CsrfMiddleware
{
    public static function handle(): void
    {
        if (CSRF::verify(CSRF::tokenFromRequest())) {
            return;
        }

        if (Request::isAjax()) {
            Response::error('Invalid or missing CSRF token. Refresh the page and try again.', 419);
        }

        http_response_code(419);
        exit('Invalid CSRF token. Please go back and try again.');
    }
}
