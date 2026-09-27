# Task Management Platform - Architecture Documentation

## Architectural Overview

The backend is built with Laravel 12 following clean domain separation, SOLID principles, and backend development best practices:

1. **Routing and HTTP Controllers** (`app/Http/Controllers/`):
   - `AuthController`: Manages user authentication, JWT token issuance, profile queries, and token revocation.
   - `TaskController`: Handles task CRUD operations, server-side pagination, status/priority filtering, keyword searching, bulk status updates, and export dispatching.
   - `AttachmentController`: Handles secure file uploads, image thumbnails, chunked large file uploads (>50MB), downloads, and deletions.
   - `CommentController`: Manages task comment listing, creation, and deletion with user role/ownership checks.
   - `QueueController`: Provides endpoints to monitor queue health and execute background jobs on demand via HTTP.

2. **Middleware Pipeline** (`app/Http/Middleware/`):
   - `HandleCors`: Laravel built-in CORS middleware configured for frontend clients.
   - `AuthenticateWithJwt`: Custom middleware validating RFC 7519 HMAC-SHA256 JWT tokens, checking token revocation via Cache, and falling back to Sanctum tokens where present.
   - `RoleMiddleware`: Enforces Role-Based Access Control (admin, manager, member).

3. **Eloquent Domain Models & Relationships** (`app/Models/`):
   - `User`: Relationships to assigned tasks, created tasks, comments, and attachments.
   - `Task`: Eager loaded relationships (`assignedUser`, `creator`, `attachments`, `comments`) preventing N+1 queries.
   - `TaskAttachment`: Relationship to `Task` and `uploader`.
   - `TaskComment`: Relationship to `Task` and `user`.
   - `FileChunk`: Temporary chunk state records for large file reassembly.

4. **Domain Services** (`app/Services/`):
   - `JwtService`: Encapsulates HMAC-SHA256 token encoding, signature verification, claims validation, and blacklist caching.
   - `FileUploadService`: File type inspection via `finfo_file`, storage sanitization, thumbnail generation, version incrementing, and chunk slice assembly.
   - `VirusScannerService`: Header inspection, EICAR pattern matching, disguised executable header detection, and script tag rejection.
   - `ImageThumbnailService`: Generates proportional 200x200 thumbnails for JPEG, PNG, GIF, and WebP using GD while preserving alpha channels.

5. **Queue and Background Processing** (`app/Jobs/`):
   - Database queue driver (`jobs` table) with attempt tracking, failed job recording, and exponential backoff retries.
   - Concrete jobs:
     - `SendTaskAssignedEmailJob`: Dispatches assignment email notifications.
     - `BulkTaskStatusUpdateJob`: Asynchronously updates statuses for batches of tasks.
     - `ProcessFileJob`: Async thumbnail generation and threat scanning.
     - `ExportDataJob`: Generates task exports (CSV/PDF reports).

## Database Entity Relationship

```
+------------------+       1:N       +------------------+
|      users       | <-------------- |      tasks       |
+------------------+                 +------------------+
| id (PK)          |                 | id (PK)          |
| name             |                 | title            |
| email (Unique)   |                 | description      |
| password         |                 | status           |
| role             |                 | priority         |
| created_at       |                 | assigned_user_id |
| updated_at       |                 | created_by (FK)  |
+------------------+                 | due_date         |
         |                           | created_at       |
         | 1:N                       | updated_at       |
         v                           +------------------+
+------------------+                   | 1:N        | 1:N
|  task_comments   | <-----------------+            |
+------------------+                                v
| id (PK)          |                     +---------------------+
| task_id (FK)     |                     |  task_attachments   |
| user_id (FK)     |                     +---------------------+
| comment          |                     | id (PK)             |
| created_at       |                     | task_id (FK)        |
+------------------+                     | file_name           |
                                         | file_path           |
                                         | file_size           |
                                         | mime_type           |
                                         | thumbnail_path      |
                                         | version             |
                                         | uploaded_by (FK)    |
                                         | uploaded_at         |
                                         +---------------------+
```

## Security Strategy

1. **Password Hashing**: Passwords are saved with bcrypt hashing via Laravel `Hash::make()` and verified using `Hash::check()`.
2. **SQL Injection Defense**: Built-in Eloquent ORM and PDO prepared statements with strict parameter binding.
3. **File Upload Security**:
   - MIME types are determined by file inspection (`finfo_file`), not by trusting user-provided extensions or client Content-Type headers.
   - Files are stored with cryptographic random UUID names outside direct public script execution contexts.
   - Files undergo virus scanning before storage.
4. **Token Revocation**: Logged-out tokens are stored in a cache blacklist and immediately rejected on subsequent calls.
