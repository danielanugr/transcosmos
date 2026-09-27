# Task Management API - Laravel 12 Backend

A production-ready REST API built with Laravel 12 providing task management, secure file attachments, image thumbnail processing, and asynchronous background queue execution.

## Features

- **Authentication**: RFC 7519 HMAC-SHA256 JWT tokens with role claims and token revocation via Cache, plus Laravel Sanctum API support.
- **Task Management**: Full CRUD with server-side pagination, status/priority filtering, keyword search, and eager-loaded Eloquent relationships (`assignedUser`, `creator`, `attachments`, `comments`).
- **File Uploads**: MIME verification via `finfo_file`, virus scanner simulation, GD thumbnail generation, automatic file versioning on duplicates, and chunked uploads (>50MB).
- **Background Queue Processing**: Native database queue driver with retries and exponential backoff for:
  - Task assignment email notifications (`SendTaskAssignedEmailJob`)
  - Bulk task status updates (`BulkTaskStatusUpdateJob`)
  - Asynchronous file threat scanning and thumbnail generation (`ProcessFileJob`)
  - CSV report exports (`ExportDataJob`)
- **Automated Tests**: Comprehensive PHPUnit feature test suite covering 19 test assertions with 100% pass rate.

## Project Structure

```
backend/
├── src/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php
│   │   │   ├── TaskController.php
│   │   │   ├── AttachmentController.php
│   │   │   ├── CommentController.php
│   │   │   └── QueueController.php
│   │   └── Middleware/
│   │       ├── AuthenticateWithJwt.php
│   │       └── RoleMiddleware.php
│   ├── Jobs/
│   │   ├── SendTaskAssignedEmailJob.php
│   │   ├── BulkTaskStatusUpdateJob.php
│   │   ├── ProcessFileJob.php
│   │   └── ExportDataJob.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Task.php
│   │   ├── TaskAttachment.php
│   │   ├── TaskComment.php
│   │   └── FileChunk.php
│   └── Services/
│       ├── JwtService.php
│       ├── FileUploadService.php
│       ├── ImageThumbnailService.php
│       └── VirusScannerService.php
├── bootstrap/
│   └── app.php
├── config/
├── database/
│   ├── migrations/
│   ├── seeders/
│   │   └── DatabaseSeeder.php
│   ├── schema.sql
│   ├── seeds.sql
│   └── dump.sql
├── routes/
│   └── api.php
├── tests/
│   └── Feature/
│       ├── AuthApiTest.php
│       ├── TaskApiTest.php
│       ├── AttachmentApiTest.php
│       └── QueueApiTest.php
├── artisan
└── composer.json
```

## Running the Application

1. **Run migrations and seed the database**:
   ```bash
   php artisan migrate:fresh --seed
   ```

2. **Run feature tests**:
   ```bash
   php artisan test
   ```

3. **Start the API development server**:
   ```bash
   php artisan serve --port=8000
   ```

4. **Run the background queue worker**:
   ```bash
   php artisan queue:work
   ```

## Seeded Test Accounts

All accounts share password: `password123`
- Admin: `alice@example.com`
- Manager: `bob@example.com`
- Member: `charlie@example.com`
- Member: `diana@example.com`
- Member: `evan@example.com`
