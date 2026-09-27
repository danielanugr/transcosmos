# Task Management Platform - Architecture Documentation

## Architectural Overview

The backend is built as a layered, modular PHP 8.3 RESTful application following domain-driven separations:

1. **Routing and HTTP Layer** (`src/Http/`):
   - `Request`: Wraps incoming HTTP headers, parameters, query strings, and file buffers.
   - `Response`: Encapsulates status codes, headers, streaming file downloads, and JSON formatting.
   - `Router`: Provides regex-based route dispatching, middleware pipeline chaining, and centralized exception translation.

2. **Middleware Pipeline** (`src/Middleware/`):
   - `CorsMiddleware`: Sets CORS headers for modern frontends.
   - `AuthMiddleware`: Validates HMAC-SHA256 JWT tokens, decodes claims, verifies revocation, and binds the authenticated user record to the request.
   - `RoleMiddleware`: Enforces Role-Based Access Control (admin, manager, member).

3. **Domain Services** (`src/Services/`):
   - `AuthService`: Handles password hashing via native bcrypt, credential verification, and token lifecycle management.
   - `TaskService`: Business logic for tasks, filter orchestration, eager relationship loading, and queue event triggering.
   - `FileUploadService`: File type inspection, size boundaries, storage sanitization, chunk reassembly, and versioning.
   - `VirusScannerService`: Header analysis, EICAR pattern detection, disguised binary detection, and script injection checks.
   - `ImageThumbnailService`: Generates proportional 200x200 thumbnails for JPEG, PNG, GIF, and WebP using GD while preserving alpha channels.

4. **Data Access Layer** (`src/Repositories/`):
   - Decouples SQL queries and PDO interactions from HTTP controllers and services.
   - Prevents N+1 query bottlenecks by joining user entities and calculating attachment/comment counts in batch queries.
   - Supports both MySQL 8.0+ and SQLite 3 through PDO.

5. **Queue and Background Processing** (`src/Queue/`):
   - Database-backed queue system for reliable persistence without external daemon requirements.
   - Supports retry attempts, exponential backoff (5s, 10s, 20s), and error logging.
   - Executable via CLI worker (`worker.php`) or on-demand HTTP invocation (`POST /api/queue/work`).

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

1. **Password Storage**: Passwords are saved as bcrypt hashes with work factor 10 using PHP `password_hash()` and verified with `password_verify()`.
2. **SQL Injection Defense**: Every dynamic parameter is bound through PDO prepared statements with explicit parameter type casting.
3. **File Upload Security**:
   - MIME types are determined by file inspection (`finfo_file`), not by trusting the user-provided file extension or Content-Type header.
   - Files are stored using cryptographic random UUID names to stop directory traversal attacks and prevent direct script execution.
   - Files are inspected by the virus scanner before storage.
4. **JWT Revocation**: Logged-out tokens are stored in a blacklist store and rejected immediately on subsequent requests.
