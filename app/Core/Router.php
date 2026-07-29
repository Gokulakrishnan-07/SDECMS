<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PermissionMiddleware;

/**
 * Minimal HTTP router with {param} placeholders and middleware pipeline.
 *
 * $router->get('/api/budgets/{id}', [BudgetController::class, 'show'], ['auth', 'can:budgets.view']);
 */
class Router
{
    /** @var array<string, array<int, array{pattern:string, params:string[], handler:array, middleware:string[]}>> */
    private array $routes = [];

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->add('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->add('DELETE', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, array $handler, array $middleware): void
    {
        $params  = [];
        $pattern = preg_replace_callback('/\{(\w+)\}/', static function ($m) use (&$params) {
            $params[] = $m[1];
            // {id} and *_id params match digits only so literal segments
            // like /export never collide with /{id} routes.
            return ($m[1] === 'id' || str_ends_with($m[1], '_id')) ? '(\d+)' : '([\w\-]+)';
        }, $path);

        $this->routes[$method][] = [
            'pattern'    => '#^' . $pattern . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];
    }

    public function dispatch(): void
    {
        $method = Request::method();
        $path   = Request::path();

        foreach ($this->routes[$method] ?? [] as $route) {
            if (preg_match($route['pattern'], $path, $matches)) {
                array_shift($matches);
                $args = array_combine($route['params'], array_slice($matches, 0, count($route['params']))) ?: [];

                $this->runMiddleware($route['middleware'], $method);

                [$class, $action] = $route['handler'];
                $controller = new $class();
                $controller->$action(...array_values($args));
                return;
            }
        }

        // Not found
        if (Request::isAjax()) {
            Response::error('Resource not found.', 404);
        }
        http_response_code(404);
        $file = BASE_PATH . '/app/Views/errors/404.php';
        if (is_file($file)) {
            require $file;
        } else {
            echo '404 — Not Found';
        }
    }

    /**
     * Middleware strings: 'auth', 'guest', 'csrf', 'can:permission.name'
     */
    private function runMiddleware(array $middleware, string $method): void
    {
        // CSRF is enforced automatically on all state-changing requests
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            CsrfMiddleware::handle();
        }

        foreach ($middleware as $entry) {
            if ($entry === 'auth') {
                AuthMiddleware::handle();
            } elseif ($entry === 'guest') {
                GuestMiddleware::handle();
            } elseif (str_starts_with($entry, 'can:')) {
                PermissionMiddleware::handle(substr($entry, 4));
            }
        }
    }
}
