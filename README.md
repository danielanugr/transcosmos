# Task Management Platform

Full-stack technical assessment implementation featuring a modern **Laravel 12 REST API** backend, **Next.js 16 (React 19)** frontend, secure chunked file handling with heuristic virus scanning, background processing queue, real-time collaboration sync, and comprehensive test coverage.

---

## 1. Project Directory Structure

```
project-root/
├── backend/
│   ├── src/                    # Controllers, Models, Services, Jobs, Middleware
│   ├── config/                 # Laravel configuration files
│   ├── database/               # Migrations, seeders, schema.sql, seeds.sql, dump.sql
│   ├── tests/                  # PHPUnit Unit & Feature test suites (41 tests)
│   └── README.md
├── frontend/
│   ├── src/                    # Next.js App Router, components, custom hooks, API client
│   ├── public/                 # Static web assets
│   ├── tests/                  # Vitest unit, component, integration, and E2E flow tests (16 tests)
│   └── README.md
├── documentation/
│   ├── api-docs/
│   │   ├── openapi.yaml        # OpenAPI 3.0 API specification
│   │   └── postman_collection.json # Postman v2.1 Collection with auto-token script
│   ├── architecture.md         # Architecture documentation and ADR-001 through ADR-005
│   ├── database-schema.md      # Database ERD, table definitions, data dictionary, cascade rules
│   ├── deployment-guide.md     # Docker Compose, Nginx SSL, Supervisor, and PM2 deployment
│   └── setup-guide.md          # Step-by-step local development setup guide
└── README.md
```

---

## 2. Technical Implementation Summary by Part

### Part 1: Laravel 12 Backend Development
- **Database Schema & Seeding**:
  - Full relational design with `users`, `tasks`, `task_attachments`, `task_comments`, `jobs`, `file_chunks`.
  - Cascading deletes, compound indexes on `(status, priority)` and `(task_id, file_name, version)`.
  - Provided in [`backend/database/schema.sql`](file:///d:/test/trans-cosmos/backend/database/schema.sql), [`backend/database/seeds.sql`](file:///d:/test/trans-cosmos/backend/database/seeds.sql), and [`backend/database/dump.sql`](file:///d:/test/trans-cosmos/backend/database/dump.sql).
- **Authentication**:
  - RFC 7519 HMAC-SHA256 JWT tokens via [`JwtService.php`](file:///d:/test/trans-cosmos/backend/src/Services/JwtService.php) with instant blacklist revocation on logout.
- **Task Management**:
  - Paginated CRUD, multi-criteria filtering (status, priority, assignee), full-text search, and batch status modifications.
- **Secure File Upload System**:
  - Magic-byte MIME detection via `finfo_file` (not client extensions).
  - Chunked file upload endpoint (`/api/tasks/{id}/attachments/chunk`) supporting files >50MB with slice assembly.
  - Automatic file versioning (`v1`, `v2`, ...) for repeated uploads.
  - Heuristic virus scanner (`VirusScannerService`) inspecting EICAR signatures, MZ/ELF executables, and script injection.
  - Automatic thumbnail generation for images and video previews using GD library.
- **Background Job Queue**:
  - Database-backed queue with locking, retry backoff, and CLI/HTTP workers (`SendTaskAssignedEmailJob`, `BulkTaskStatusUpdateJob`, `ProcessFileJob`, `ExportDataJob`).

---

### Part 2: Next.js 16 Frontend Development
- **Core Technology**:
  - Next.js 16 (App Router), React 19, TypeScript, Tailwind CSS, Lucide icons.
- **State Management & Context**:
  - Global `AuthProvider` for JWT lifecycle and `ToastProvider` for asynchronous user feedback.
- **Task Dashboard & Modals**:
  - Task cards with status and priority badges, search keyword filters, bulk selection toolbar, and statistics counters.
  - Task creation and edit modal with full form validation.
  - Detail modal with tabbed views for comments and file attachments.
- **File Upload Zone**:
  - Drag-and-drop file dropzone with chunked slice uploader (>5MB) showing progress bar and percentage.
  - Attachment list with thumbnail previews, download actions, and version indicators.
- **Interactive Comment Thread**:
  - Live comment listing and submission with `Ctrl/Cmd + Enter` shortcut.

---

### Part 3: Advanced Real-Time & Performance Features
- **Real-Time Collaboration**:
  - Live presence heartbeat showing active online users.
  - Typing indicator broadcasting ("User is typing...").
  - Adaptive delta-sync polling (20-second cadence, paused when tab is inactive via `document.hidden`).
- **HTTP 206 Partial Content Video Streaming**:
  - Dedicated endpoint (`/api/attachments/{id}/stream`) supporting byte-range requests for video playback seeking.
- **Robust Cache Layer**:
  - Controller-level caching with array serialization (`$paginator->getCollection()->toArray()`) avoiding incomplete PHP class deserialization.

---

### Part 4: Documentation & Testing Deliverables

#### 4.1 Documentation
- **Setup Guide**: [`documentation/setup-guide.md`](file:///d:/test/trans-cosmos/documentation/setup-guide.md)
- **API Documentation (OpenAPI 3.0)**: [`documentation/api-docs/openapi.yaml`](file:///d:/test/trans-cosmos/documentation/api-docs/openapi.yaml)
- **API Documentation (Postman Collection)**: [`documentation/api-docs/postman_collection.json`](file:///d:/test/trans-cosmos/documentation/api-docs/postman_collection.json)
- **Database Schema Documentation**: [`documentation/database-schema.md`](file:///d:/test/trans-cosmos/documentation/database-schema.md)
- **Architecture Documentation & ADRs**: [`documentation/architecture.md`](file:///d:/test/trans-cosmos/documentation/architecture.md)
- **Production Deployment Guide**: [`documentation/deployment-guide.md`](file:///d:/test/trans-cosmos/documentation/deployment-guide.md)

#### 4.2 Automated Test Coverage (57 Tests Total)
- **Backend Tests (41 PHPUnit Tests - 100% Passing)**:
  - Unit: `VirusScannerServiceTest`, `ImageThumbnailServiceTest`, `JwtServiceTest`
  - Feature: `TaskApiTest`, `AttachmentApiTest`, `AuthApiTest`, `DatabaseIntegrationTest`, `QueueApiTest`, `BonusFeaturesTest`
- **Frontend Tests (16 Vitest Tests - 100% Passing)**:
  - Domain & Type rules: `domain.test.ts`
  - Component & Stats logic: `components.test.ts`
  - API Client & Error handling: `api.test.ts`
  - Bonus Features & Realtime: `bonus.test.ts`
  - End-to-End User Flow simulation: `userFlows.test.ts`

---

## 3. Quick Start Commands

### Run Backend & Tests
```bash
cd backend
composer install
php artisan migrate:fresh --seed
php artisan test
php artisan serve --port=8000
```

### Run Frontend & Tests
```bash
cd frontend
npm install
npm test
npm run build
npm run dev
```

### Access Application
- Web Dashboard: `http://localhost:3000`
- API Base: `http://127.0.0.1:8000/api`
- Default Admin Account: `alice@example.com` / `password123`
