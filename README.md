# Task Management Platform

Full-stack technical assessment implementation featuring a modern Laravel 12 REST API backend, secure file handling with chunking and versioning, and an asynchronous background processing queue.

## Project Structure

```
project-root/
├── backend/
│   ├── src/
│   ├── config/
│   ├── database/
│   ├── tests/
│   └── README.md
├── frontend/
│   ├── src/
│   ├── public/
│   ├── tests/
│   └── README.md
├── README.md
└── documentation/
    ├── api-docs/
    │   └── openapi.yaml
    ├── architecture.md
    └── setup-guide.md
```

## Part 1 Implementation Summary (Laravel 12)

### 1.1 Database Design & Setup
- Entity tables implemented: `users`, `tasks`, `task_attachments`, `task_comments`, `jobs`, `file_chunks`, `personal_access_tokens`.
- Full MySQL schema with indexes, foreign keys, and cascading rules in [`backend/database/schema.sql`](file:///d:/test/trans-cosmos/backend/database/schema.sql).
- Complete seed data with 5 users, 15 tasks, 10 comments, and attachments in [`backend/database/seeds.sql`](file:///d:/test/trans-cosmos/backend/database/seeds.sql) and [`backend/database/seeders/DatabaseSeeder.php`](file:///d:/test/trans-cosmos/backend/database/seeders/DatabaseSeeder.php).
- Unified SQL dump file in [`backend/database/dump.sql`](file:///d:/test/trans-cosmos/backend/database/dump.sql).

### 1.2 RESTful API Development
- **Authentication**: `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me` with RFC 7519 HMAC-SHA256 JWT tokens.
- **Task Management**: `GET /api/tasks` (with pagination, sorting, status/priority filtering, keyword search, eager loaded relationships), `POST /api/tasks`, `GET /api/tasks/{id}`, `PUT /api/tasks/{id}`, `DELETE /api/tasks/{id}`.
- **Bulk Updates**: `POST /api/tasks/bulk-status` supporting asynchronous queue processing.
- **Data Export**: `POST /api/tasks/export` exporting CSV reports via queue.
- **File Management**: `POST /api/tasks/{id}/attachments`, `GET /api/attachments/{id}/download`, `DELETE /api/attachments/{id}`.
- **Task Comments**: `GET /api/tasks/{id}/comments`, `POST /api/tasks/{id}/comments`, `DELETE /api/comments/{id}`.

### 1.3 Secure File Upload System
- Inspection of real file MIME types using `finfo_file` against an allowed whitelist.
- File size boundary checks and filename sanitization.
- Image thumbnail generation using PHP GD library preserving alpha transparency.
- **Advanced Challenge: Large Chunked Uploads (>50MB)**: `POST /api/tasks/{id}/attachments/chunk` reassembles sliced binary chunks and verifies file integrity.
- **Advanced Challenge: Virus Scanning Simulation**: `VirusScannerService` inspects headers, blocks EICAR signatures, detects disguised executable binaries, and flags embedded script tags.
- **Advanced Challenge: File Versioning System**: Automatically detects duplicate filenames on a task and increments the version counter.

### 1.4 Background Job Processing
- Durable database-backed queue table (`jobs`) with locking, status states, attempt tracking, and exponential backoff retry.
- Concrete jobs:
  - `SendTaskAssignedEmailJob`: Dispatches email notification when a task is assigned.
  - `BulkTaskStatusUpdateJob`: Handles batch status modifications.
  - `ProcessFileJob`: Async thumbnail generation and virus scanning.
  - `ExportDataJob`: Generates task exports (CSV reports).
- Worker processes: CLI runner `php artisan queue:work` and HTTP invocation endpoint `POST /api/queue/work`.

## Quick Start Verification

Run the test suite from `backend/`:
```bash
cd backend
php artisan test
```
