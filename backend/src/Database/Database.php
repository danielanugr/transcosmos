<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $connection = config('database.default', 'sqlite');
        $config = config("database.connections.{$connection}");

        if (!$config) {
            throw new RuntimeException("Database configuration for [{$connection}] not found.");
        }

        try {
            if ($config['driver'] === 'sqlite') {
                $dbPath = $config['database'];
                $dir = dirname($dbPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $dsn = "sqlite:{$dbPath}";
                $pdo = new PDO($dsn, null, null, $config['options'] ?? []);
                $pdo->exec('PRAGMA foreign_keys = ON;');
            } elseif ($config['driver'] === 'mysql') {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $config['host'],
                    $config['port'],
                    $config['database'],
                    $config['charset'] ?? 'utf8mb4'
                );
                $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? []);
            } else {
                throw new RuntimeException("Unsupported database driver: {$config['driver']}");
            }

            self::$instance = $pdo;
            return self::$instance;
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection error: " . $e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::getConnection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
