<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Role-based access control: aborts when the user lacks a permission.
 */
class PermissionMiddleware
{
    public static function handle(string $permission): void
    {
        if (Auth::can($permission)) {
            return;
        }

        if (Request::isAjax()) {
            Response::error('You do not have permission to perform this action.', 403);
        }

        http_response_code(403);
        require BASE_PATH . '/app/Views/errors/403.php';
        exit;
    }
}
