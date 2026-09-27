<?php

declare(strict_types=1);

// Standalone CLI Queue Worker
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'App\\';
        $baseDir = __DIR__ . '/src/';
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
    require_once __DIR__ . '/src/Utils/helpers.php';
}

use App\Queue\QueueManager;
use App\Utils\Env;

Env::load(__DIR__ . '/.env');

$options = getopt('', ['queue:', 'max:', 'sleep:']);
$queue = $options['queue'] ?? 'default';
$maxJobs = isset($options['max']) ? (int) $options['max'] : 0;
$sleep = isset($options['sleep']) ? (int) $options['sleep'] : 2;

echo "========================================\n";
echo " Task Management Queue Worker CLI\n";
echo " Queue: {$queue}\n";
echo " Time:  " . date('Y-m-d H:i:s') . "\n";
echo "========================================\n";

$manager = new QueueManager();
$manager->runWorker($queue, $maxJobs, $sleep);
