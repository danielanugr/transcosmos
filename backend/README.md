# Task Management API - Backend

A clean, modular PHP REST API supporting task management, secure file attachments, image thumbnails, and background queues.

## Feature Matrix

- **Authentication**: JWT authentication (HMAC-SHA256) with token blacklisting on logout and RBAC middleware.
- **Task Management**: Full CRUD with server-side pagination, status/priority filtering, keyword search, and relational joins.
- **File Uploads**: MIME verification via `finfo`, virus scan simulation, GD thumbnail generation, file versioning, and chunked uploads (>50MB).
- **Background Queue**: Database-backed job queue with retries, exponential backoff, and asynchronous workers for emails, status updates, thumbnails, and exports.
- **Testing**: Automated test suite covering auth, task CRUD, uploads, versioning, chunking, and queues.

## Directory Structure

```
backend/
├── config/             Configuration files (app, database)
├── database/           SQL schema, seeds, full dump, and seeder script
├── public/             Front controller (index.php) and rewrite rules
├── src/
│   ├── Auth/           JWT service and security logic
│   ├── Controllers/    API route controllers
│   ├── Database/       Database connection and transaction manager
│   ├── Http/           Request, Response, and Router
│   ├── Middleware/     CORS, Auth, and Role-based access control
│   ├── Queue/          Queue manager, job interfaces, and concrete jobs
│   ├── Repositories/   Data access layer (Users, Tasks, Attachments, Comments, Jobs)
│   ├── Services/       Business logic, file uploads, virus scanning, thumbnails
│   └── Utils/          Env parser, structured logger, input validator, helpers
├── storage/            File uploads, thumbnails, temporary chunks, exports, logs
├── tests/              Integration test suites and test runner
└── worker.php          CLI queue worker process
```

## Running the Application

1. **Seed the database**:
   ```bash
   php database/Seeder.php
   ```

2. **Run tests**:
   ```bash
   php tests/run_tests.php
   ```

3. **Start the API server**:
   ```bash
   php -S 127.0.0.1:8000 -t public
   ```

4. **Run background queue worker**:
   ```bash
   php worker.php
   ```

## Test Accounts

All accounts use password: `password123`
- Admin: `alice@example.com`
- Manager: `bob@example.com`
- Member: `charlie@example.com`
