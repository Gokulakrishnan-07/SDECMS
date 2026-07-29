<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Base controller: view rendering with layout support.
 */
abstract class Controller
{
    /**
     * Render a view inside a layout. Data keys become local variables.
     */
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = BASE_PATH . '/app/Views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            exit('View not found: ' . e($view));
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
        if ($layout && is_file($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Common pagination inputs for list endpoints.
     *
     * @return array{page:int, perPage:int, offset:int, search:string}
     */
    protected function pageParams(int $defaultPerPage = 10): array
    {
        $page    = max(1, (int) Request::query('page', 1));
        $perPage = min(100, max(1, (int) Request::query('per_page', $defaultPerPage)));
        return [
            'page'    => $page,
            'perPage' => $perPage,
            'offset'  => ($page - 1) * $perPage,
            'search'  => trim((string) Request::query('search', '')),
        ];
    }
}
