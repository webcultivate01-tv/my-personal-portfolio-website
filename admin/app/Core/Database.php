<?php
namespace App\Core;

use PDO;
use PDOException;

/**
 * Thin PDO wrapper. Gives a single shared connection (singleton)
 * so every Model talks to the same database handle.
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        try {
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            $msg = DEBUG ? $e->getMessage() : 'Database connection failed.';
            exit('DB error: ' . htmlspecialchars($msg));
        }

        return self::$pdo;
    }
}
