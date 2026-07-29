<?php

/**
 * Swami Dayananda Educational Cost Management System (SECMS)
 * Front Controller — all HTTP requests are routed through this file.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap.php';

use App\Core\Router;

$router = new Router();

require BASE_PATH . '/app/routes.php';

$router->dispatch();
