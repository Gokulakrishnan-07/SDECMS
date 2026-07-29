<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Standardised response helpers. All API responses share one JSON envelope:
 * { success, message, data, errors, meta }
 */
class Response
{
    public static function json(mixed $data = null, int $status = 200, string $message = 'OK', array $meta = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $status >= 200 && $status < 300,
            'message' => $message,
            'data'    => $data,
            'meta'    => (object) $meta,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(string $message, int $status = 400, array $errors = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors'  => (object) $errors,
            'data'    => null,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Paginated collection response.
     */
    public static function paginated(array $items, int $total, int $page, int $perPage): never
    {
        self::json($items, 200, 'OK', [
            'pagination' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => max(1, (int) ceil($total / max(1, $perPage))),
            ],
        ]);
    }

    /**
     * Stream a CSV download built from rows of associative arrays.
     */
    public static function csv(string $filename, array $headers, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        // UTF-8 BOM so Excel opens it correctly
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
        exit;
    }

    /**
     * Excel-compatible download (HTML table served as .xls — opens in Excel
     * without external libraries).
     */
    public static function excel(string $filename, array $headers, array $rows, string $title = ''): never
    {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"></head><body>';
        if ($title !== '') {
            echo '<h3>' . e($title) . '</h3>';
        }
        echo '<table border="1"><thead><tr>';
        foreach ($headers as $h) {
            echo '<th style="background:#f2f2f2">' . e($h) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $row) {
            echo '<tr>';
            foreach ($row as $cell) {
                echo '<td>' . e($cell) . '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></body></html>';
        exit;
    }
}
