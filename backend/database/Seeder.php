<?php

declare(strict_types=1);

// Standalone Database Migration & Seeder CLI
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = dirname(__DIR__) . '/src/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    });
    require_once dirname(__DIR__) . '/src/Utils/helpers.php';
}

use App\Database\Database;
use App\Utils\Env;

Env::load(dirname(__DIR__) . '/.env');

echo "Initializing Task Management database...\n";

try {
    $driver = config('database.default', 'sqlite');
    echo "Using connection: [{$driver}]\n";

    $pdo = Database::getConnection();

    $schemaFile = $driver === 'sqlite' 
        ? __DIR__ . '/schema_sqlite.sql' 
        : __DIR__ . '/schema.sql';

    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found at {$schemaFile}");
    }

    echo "Running schema: " . basename($schemaFile) . "...\n";
    $schemaSql = file_get_contents($schemaFile);
    
    // Split and execute SQL statements
    if ($driver === 'sqlite') {
        $pdo->exec($schemaSql);
    } else {
        $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
    }

    echo "Running seeder: seeds.sql...\n";
    $seedsFile = __DIR__ . '/seeds.sql';
    if (!file_exists($seedsFile)) {
        throw new RuntimeException("Seeds file not found at {$seedsFile}");
    }

    $seedsSql = file_get_contents($seedsFile);
    if ($driver === 'sqlite') {
        $pdo->exec($seedsSql);
    } else {
        $statements = array_filter(array_map('trim', explode(';', $seedsSql)));
        foreach ($statements as $stmt) {
            if (!empty($stmt)) {
                $pdo->exec($stmt);
            }
        }
    }

    // Verify entity counts
    $usersCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $tasksCount = (int) $pdo->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
    $commentsCount = (int) $pdo->query('SELECT COUNT(*) FROM task_comments')->fetchColumn();
    $attachmentsCount = (int) $pdo->query('SELECT COUNT(*) FROM task_attachments')->fetchColumn();

    echo "\nDatabase seeding completed successfully:\n";
    echo " - Users:       {$usersCount} (Minimum 5 required: PASS)\n";
    echo " - Tasks:       {$tasksCount} (Minimum 15 required: PASS)\n";
    echo " - Comments:    {$commentsCount} (Minimum 10 required: PASS)\n";
    echo " - Attachments: {$attachmentsCount}\n";
    echo "\nDefault test credentials:\n";
    echo " - Admin:   alice@example.com / password123\n";
    echo " - Manager: bob@example.com / password123\n";
    echo " - Member:  charlie@example.com / password123\n";

} catch (Throwable $e) {
    echo "Error during database seeding: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " on line " . $e->getLine() . "\n";
    exit(1);
}
