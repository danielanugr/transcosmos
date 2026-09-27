<?php

declare(strict_types=1);

return [
    'name' => env('APP_NAME', 'Task Management API'),
    'env' => env('APP_ENV', 'development'),
    'debug' => (bool) env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://127.0.0.1:8000'),

    'jwt' => [
        'secret' => env('JWT_SECRET', 'task-management-secret-key-change-in-production-32char-min!'),
        'ttl' => (int) env('JWT_TTL', 86400), // 24 hours in seconds
        'algorithm' => 'HS256',
    ],

    'upload' => [
        'max_file_size' => (int) env('MAX_FILE_SIZE', 52428800), // 50MB in bytes
        'storage_path' => storage_path('uploads'),
        'thumbnail_path' => storage_path('uploads/thumbnails'),
        'temp_path' => storage_path('temp'),
        'allowed_mimes' => [
            // Images
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain',
            'text/csv',
            // Videos
            'video/mp4',
            'video/webm',
            'video/ogg',
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv',
            'mp4', 'webm', 'ogg'
        ],
    ],

    'queue' => [
        'max_attempts' => (int) env('QUEUE_MAX_ATTEMPTS', 3),
        'retry_delay_seconds' => (int) env('QUEUE_RETRY_DELAY', 5),
    ],
];
