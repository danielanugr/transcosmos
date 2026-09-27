<?php

declare(strict_types=1);

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\RealtimeController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    return response()->json([
        'success' => true,
        'message' => 'Task Management API is online.',
        'data' => [
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'php_version' => PHP_VERSION,
            'framework' => 'Laravel ' . app()->version(),
        ],
    ]);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth.jwt');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth.jwt');
});

Route::get('/tasks', [TaskController::class, 'index']);
Route::get('/tasks/{id}', [TaskController::class, 'show']);

Route::middleware('auth.jwt')->group(function () {
    Route::post('/tasks', [TaskController::class, 'store']);
    Route::put('/tasks/{id}', [TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [TaskController::class, 'destroy']);
    Route::post('/tasks/bulk-status', [TaskController::class, 'bulkStatus']);
    Route::post('/tasks/export', [TaskController::class, 'export']);

    Route::post('/tasks/{id}/attachments', [AttachmentController::class, 'upload']);
    Route::post('/tasks/{id}/attachments/chunk', [AttachmentController::class, 'uploadChunk']);
    Route::delete('/attachments/{id}', [AttachmentController::class, 'destroy']);
    Route::delete('/attachments/{id}/delete', [AttachmentController::class, 'destroy']);

    Route::post('/tasks/{id}/comments', [CommentController::class, 'store']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

    Route::post('/queue/work', [QueueController::class, 'work']);
    Route::post('/realtime/presence', [RealtimeController::class, 'updatePresence']);
    Route::post('/realtime/typing', [RealtimeController::class, 'typing']);
});

Route::get('/attachments/{id}/download', [AttachmentController::class, 'download']);
Route::get('/attachments/{id}/stream', [AttachmentController::class, 'stream']);
Route::get('/tasks/{id}/comments', [CommentController::class, 'index']);
Route::get('/queue/stats', [QueueController::class, 'stats']);

Route::get('/realtime/stream', [RealtimeController::class, 'stream']);
Route::get('/realtime/presence', [RealtimeController::class, 'getPresence']);

Route::get('/cache/stats', function (\App\Services\AdvancedCacheService $cache) {
    return response()->json([
        'success' => true,
        'data' => [
            'version' => $cache->getVersion(),
            'telemetry' => $cache->getTelemetryStats(),
        ],
    ]);
});

Route::post('/cache/flush', function (\App\Services\AdvancedCacheService $cache) {
    $newVersion = $cache->invalidateNamespace();
    return response()->json([
        'success' => true,
        'message' => 'Cache namespace bumped and invalidated successfully.',
        'new_version' => $newVersion,
    ]);
})->middleware('auth.jwt');
