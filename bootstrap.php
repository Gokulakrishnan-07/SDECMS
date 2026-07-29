<?php

/**
 * Application bootstrap: autoloading, configuration, error handling, session.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
// PSR-4 style autoloader:  App\  =>  app/
// ---------------------------------------------------------------------------
spl_autoload_register(static function (string $class): void {
    $prefix  = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    if (str_starts_with($class, $prefix)) {
        $relative = substr($class, strlen($prefix));
        $file     = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

// ---------------------------------------------------------------------------
// Configuration & helpers (helpers define config(), used everywhere below)
// ---------------------------------------------------------------------------
$GLOBALS['config'] = require BASE_PATH . '/config/config.php';

require BASE_PATH . '/app/Helpers/helpers.php';

// ---------------------------------------------------------------------------
// Error handling
// ---------------------------------------------------------------------------
if (config('app.debug')) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
}

ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

date_default_timezone_set(config('app.timezone'));

// ---------------------------------------------------------------------------
// Session (secure cookie settings + timeout handled in Session::start)
// ---------------------------------------------------------------------------
App\Core\Session::start();
