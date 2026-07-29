<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * PDO connection singleton. All queries go through prepared statements.
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $cfg = config('db');
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['port'],
                $cfg['database'],
                $cfg['charset']
            );

            try {
                self::$instance = new PDO($dsn, $cfg['username'], $cfg['password'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                if (config('app.debug')) {
                    exit('Database connection failed: ' . e($e->getMessage()));
                }
                exit('Database connection failed. Please contact the administrator.');
            }
        }

        return self::$instance;
    }

    /**
     * Run a callback inside a transaction, rolling back on any exception.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
