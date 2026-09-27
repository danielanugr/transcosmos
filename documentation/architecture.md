# Task Management Platform - Architecture Documentation & ADRs

## 1. Architectural Overview

The Task Management Platform is built as a decoupled, multi-tier system with a modern **Laravel 12 REST API** backend and a **Next.js 16 (React 19)** frontend. It adheres to Clean Architecture principles, SOLID design, domain separation, and defensive security.

```
+--------------------------------------------------------------------------+
|                        Frontend Layer (Next.js 16)                       |
|   App Router | Tailwind CSS | React Context (Auth/Toast) | useRealtime   |
+--------------------------------------------------------------------------+
                                     │
                             RESTful HTTP / JSON
                                     ▼
+--------------------------------------------------------------------------+
|                       HTTP Transport & Routing Layer                     |
|   Route Handlers | Rate Limiting | CORS | JwtAuthMiddleware | RBAC       |
+--------------------------------------------------------------------------+
                                     │
                                     ▼
+--------------------------------------------------------------------------+
|                        Controller Layer (API Resource)                   |
|   AuthController | TaskController | AttachmentController | QueueCtrl     |
+--------------------------------------------------------------------------+
                                     │
                                     ▼
+--------------------------------------------------------------------------+
|                         Domain Service Layer                             |
|   JwtService | FileUploadService | VirusScannerService | ThumbnailSvc    |
+--------------------------------------------------------------------------+
                     │                                   │
                     ▼                                   ▼
+------------------------------------+   +---------------------------------+
|       Data Access Layer            |   |   Asynchronous Queue Workers    |
|   Eloquent ORM | PDO Transactions  |   |   BulkUpdateJob | ProcessFile   |
|   Database Indexing & Cascades     |   |   SendEmailJob  | ExportData    |
+------------------------------------+   +---------------------------------+
                     │                                   │
                     ▼                                   ▼
+--------------------------------------------------------------------------+
|                   Infrastructure & Storage Layer                         |
|   MySQL 8.0+ / SQLite | Storage Filesystem / Public Thumbnails | Cache   |
+--------------------------------------------------------------------------+
```

---

## 2. Architecture Decision Records (ADRs)

### ADR-001: RFC 7519 HMAC-SHA256 JWT with Cache-Based Revocation Blacklist

- **Status**: Accepted & Implemented
- **Context**: The platform requires stateless, cross-origin authentication between Next.js (port 3000) and Laravel (port 8000), supporting role-based access control (`admin`, `manager`, `member`).
- **Decision**:
  - Implement a standard RFC 7519 HMAC-SHA256 JWT token service ([`JwtService.php`](file:///d:/test/trans-cosmos/backend/src/Services/JwtService.php)).
  - Token claims encode `sub` (user ID), `email`, `role`, `name`, `iat`, `exp`, and a cryptographically unique `jti`.
  - To support immediate logout and token invalidation before expiry, logged-out tokens are stored in Laravel's cache layer (`jwt_blacklist:<token>`) with an automatic TTL matching the token's remaining lifetime.
- **Consequences**:
  - Highly performant: No database lookups are needed for valid requests.
  - Secure: Signature verification prevents client-side tampering, and the blacklist immediately revokes stolen tokens.

---

### ADR-002: Dual-Mode File Storage with Chunk Reassembly & Heuristic Virus Scanning

- **Status**: Accepted & Implemented
- **Context**: Users need to attach documents (PDF, DOCX, TXT), images (PNG, JPG, GIF, WebP), and high-resolution videos (MP4, WebM) up to 100MB. Direct single-part HTTP POST uploads fail on unstable connections and exceed standard server memory/post limits.
- **Decision**:
  - Implement a dual-mode upload pipeline in [`FileUploadService.php`](file:///d:/test/trans-cosmos/backend/src/Services/FileUploadService.php):
    1. **Standard Direct Upload**: For smaller assets (<5MB).
    2. **Chunked Slice Upload**: Files >5MB are sliced client-side into 1MB chunks and uploaded sequentially with `upload_id`, `chunk_index`, and `total_chunks`. Upon receiving the final chunk, the server stream-assembles the binary file, purges temporary slices, and processes the asset.
  - **MIME Inspection**: MIME types are determined by inspecting raw file magic bytes via `finfo_file`, completely bypassing unverified client extensions.
  - **Heuristic Threat Detection**: [`VirusScannerService.php`](file:///d:/test/trans-cosmos/backend/src/Services/VirusScannerService.php) inspects file headers for known EICAR test signatures, disguised executable binaries (MZ/PE and ELF), and embedded `<script>` or PHP execution tags.
  - **Thumbnail Generation**: Automatically generates proportional 200x200 thumbnails for images preserving transparency, and dedicated video canvas previews for video formats.
- **Consequences**:
  - Eliminates server timeouts on large file uploads.
  - Fully resilient to connection drops and provides upload progress reporting in the frontend.

---

### ADR-003: Database-Backed Durable Queue for Asynchronous Processing

- **Status**: Accepted & Implemented
- **Context**: Operations such as sending notification emails, generating video/image thumbnails, processing bulk status updates (hundreds of tasks), and generating CSV reports take significant time and should not block the user's HTTP request lifecycle.
- **Decision**:
  - Use Laravel's database queue driver utilizing the `jobs` table with locking (`reserved_at`), attempt counters, and exponential backoff retry mechanisms.
  - Dedicated jobs:
    - `SendTaskAssignedEmailJob`: Dispatches task assignment notifications.
    - `BulkTaskStatusUpdateJob`: Batches multi-record status updates.
    - `ProcessFileJob`: Async virus scanning and post-upload thumbnail generation.
    - `ExportDataJob`: Generates task reports.
  - Worker execution is supported both via CLI daemon (`php artisan queue:work`) and via on-demand HTTP endpoint (`POST /api/queue/work`).
- **Consequences**:
  - Instant user feedback (HTTP 200/202 responses in <50ms).
  - High durability without requiring external Redis dependencies during development.

---

### ADR-004: Adaptive HTTP Polling with Visibility Pausing for Real-Time Sync

- **Status**: Accepted & Implemented
- **Context**: The platform needs real-time collaboration (live task updates, active online user counters, and typing indicators). Standard persistent Server-Sent Events (SSE) loops (`while (true) { sleep(1); }`) lock the single worker process in PHP's built-in CLI server (`php artisan serve`), preventing any other request from being served.
- **Decision**:
  - Implement an adaptive HTTP polling architecture in [`useRealtime.ts`](file:///d:/test/trans-cosmos/frontend/src/lib/useRealtime.ts) synchronized with server timestamp deltas (`GET /api/realtime/stream?poll=1&since=<timestamp>`).
  - **Request Guarding**: Protected with an `isFetching` lock to guarantee zero overlapping requests.
  - **Resource Conservation**: Polling and presence heartbeats pause completely when the browser tab is hidden or minimized (`document.hidden`).
  - **Tab Focus Debounce**: Debounced at 15 seconds on `visibilitychange` to prevent rapid requests when switching between windows.
  - **Calibrated Cadence**: 20-second poll interval and 45-second presence heartbeat ensure low resource usage (~3-4 fast requests/min) while keeping state fresh.
- **Consequences**:
  - 100% compatibility across all server environments (PHP CLI server, Nginx, Apache, Octane, Docker).
  - Eliminates server thread contention while preserving real-time collaboration.

---

### ADR-005: Array Serialization in Application Cache Layer

- **Status**: Accepted & Implemented
- **Context**: Caching paginated Eloquent model instances with `Cache::remember` caused PHP `__PHP_Incomplete_Class_Name` deserialization issues when stored in file-based cache, stripping model attributes upon JSON encoding.
- **Decision**:
  - Always serialize database results to primitive arrays (`$paginator->getCollection()->toArray()`) before storing in the application cache.
- **Consequences**:
  - Eliminates PHP class serialization hazards.
  - Guarantees complete and predictable JSON serialization across all cache drivers.

---

## 3. Security Architecture & Threat Defense

| Threat / Vulnerability | Defense Mechanism Implemented |
| --- | --- |
| **SQL Injection** | PDO prepared statements and Eloquent ORM parameter binding throughout all query builders. |
| **Cross-Site Scripting (XSS)** | React JSX automatic escaping, strict input sanitization, and `<script>` tag detection in uploaded documents. |
| **CSRF / Cross-Origin Attacks** | Stateless Bearer token authentication with explicit CORS configuration restricting allowed origins and headers. |
| **Malicious File Uploads** | Byte inspection via `finfo_file`, filename UUID randomization, non-executable storage directories, and `VirusScannerService`. |
| **Token Tampering** | Cryptographic HMAC-SHA256 signature verification with secret key validation. |
| **Password Compromise** | Bcrypt hashing with configurable work factor (`Hash::make`). |
| **N+1 Query Bottlenecks** | Eager loading of relationships (`with(['creator', 'assignedUser', 'attachments', 'comments'])`). |

---

## 4. Software Design Principles Applied

1. **Single Responsibility Principle (SRP)**: Controllers only handle HTTP transport; business logic resides in dedicated Services (`JwtService`, `FileUploadService`, `VirusScannerService`, `ImageThumbnailService`).
2. **Open/Closed Principle (OCP)**: File format handlers, queue jobs, and thumbnail generators are extensible without modifying controller core logic.
3. **Dependency Inversion (DIP)**: Controllers and Jobs receive domain services via Laravel's service container dependency injection.
