<?php

declare(strict_types=1);

// 1. Autoloading (Composer or standalone PSR-4 fallback)
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

use App\Controllers\AuthController;
use App\Controllers\TaskController;
use App\Controllers\AttachmentController;
use App\Controllers\CommentController;
use App\Controllers\QueueController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\CorsMiddleware;
use App\Utils\Env;

// 2. Load Environment Variables
Env::load(dirname(__DIR__) . '/.env');

// 3. Initialize HTTP Request
$request = Request::createFromGlobals();

// 4. Configure Router
$router = new Router();
$router->use(CorsMiddleware::class);

// Health check endpoint
$router->get('/api/health', function () {
    return Response::success([
        'status' => 'healthy',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION,
        'environment' => config('app.env', 'production'),
    ], 'Task Management API is online.');
});

// Authentication Endpoints
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me'], [AuthMiddleware::class]);

// Task Management Endpoints
$router->get('/api/tasks', [TaskController::class, 'index']);
$router->get('/api/tasks/{id}', [TaskController::class, 'show']);
$router->post('/api/tasks', [TaskController::class, 'store'], [AuthMiddleware::class]);
$router->put('/api/tasks/{id}', [TaskController::class, 'update'], [AuthMiddleware::class]);
$router->delete('/api/tasks/{id}', [TaskController::class, 'destroy'], [AuthMiddleware::class]);
$router->post('/api/tasks/bulk-status', [TaskController::class, 'bulkStatus'], [AuthMiddleware::class]);
$router->post('/api/tasks/export', [TaskController::class, 'export'], [AuthMiddleware::class]);

// File Upload Endpoints
$router->post('/api/tasks/{id}/attachments', [AttachmentController::class, 'upload'], [AuthMiddleware::class]);
$router->post('/api/tasks/{id}/attachments/chunk', [AttachmentController::class, 'uploadChunk'], [AuthMiddleware::class]);
$router->get('/api/attachments/{id}/download', [AttachmentController::class, 'download']);
$router->delete('/api/attachments/{id}', [AttachmentController::class, 'destroy'], [AuthMiddleware::class]);
$router->delete('/api/attachments/{id}/delete', [AttachmentController::class, 'destroy'], [AuthMiddleware::class]);

// Task Comments Endpoints
$router->get('/api/tasks/{id}/comments', [CommentController::class, 'index']);
$router->post('/api/tasks/{id}/comments', [CommentController::class, 'store'], [AuthMiddleware::class]);
$router->delete('/api/comments/{id}', [CommentController::class, 'destroy'], [AuthMiddleware::class]);

// Background Queue Monitoring & Execution
$router->get('/api/queue/stats', [QueueController::class, 'stats']);
$router->post('/api/queue/work', [QueueController::class, 'work'], [AuthMiddleware::class]);

// 5. Dispatch Request & Send Response
$response = $router->dispatch($request);
$response->send();
