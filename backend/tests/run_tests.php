<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once __DIR__ . '/AuthTest.php';
require_once __DIR__ . '/TaskTest.php';
require_once __DIR__ . '/FileUploadTest.php';
require_once __DIR__ . '/QueueTest.php';

use App\Utils\Env;
use Tests\AuthTest;
use Tests\TaskTest;
use Tests\FileUploadTest;
use Tests\QueueTest;

Env::load(dirname(__DIR__) . '/.env');

echo "========================================================\n";
echo " Task Management Platform - Backend Test Suite\n";
echo " Timestamp: " . date('Y-m-d H:i:s') . "\n";
echo " PHP Version: " . PHP_VERSION . "\n";
echo "========================================================\n\n";

$start = microtime(true);
$failed = 0;

$suites = [
    'Authentication Suite' => new AuthTest(),
    'Task Management Suite' => new TaskTest(),
    'File Upload & Processing Suite' => new FileUploadTest(),
    'Background Queue Suite' => new QueueTest(),
];

foreach ($suites as $name => $suite) {
    try {
        $suite->run();
    } catch (\Throwable $e) {
        $failed++;
        echo "FAILED: {$name}\n";
        echo "Error: " . $e->getMessage() . "\n";
        echo "Location: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    }
}

$duration = round(microtime(true) - $start, 3);
echo "========================================================\n";
if ($failed === 0) {
    echo "ALL TEST SUITES PASSED! (Duration: {$duration}s)\n";
    echo "========================================================\n";
    exit(0);
} else {
    echo "{$failed} TEST SUITE(S) FAILED! (Duration: {$duration}s)\n";
    echo "========================================================\n";
    exit(1);
}
