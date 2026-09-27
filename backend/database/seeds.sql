-- Sample Seed Data for Task Management Platform
-- Users (password for all seeded accounts is: password123)

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Alice Johnson', 'alice@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'admin', '2026-09-01 08:00:00', '2026-09-01 08:00:00'),
(2, 'Bob Smith', 'bob@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'manager', '2026-09-01 08:30:00', '2026-09-01 08:30:00'),
(3, 'Charlie Brown', 'charlie@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'member', '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
(4, 'Diana Prince', 'diana@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'member', '2026-09-01 09:30:00', '2026-09-01 09:30:00'),
(5, 'Evan Wright', 'evan@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'member', '2026-09-01 10:00:00', '2026-09-01 10:00:00');

-- 15 Tasks
INSERT INTO `tasks` (`id`, `title`, `description`, `status`, `priority`, `assigned_user_id`, `created_by`, `due_date`, `created_at`, `updated_at`) VALUES
(1, 'Set up production server architecture', 'Provision cloud instances and configure web servers and firewalls.', 'completed', 'high', 1, 1, '2026-09-10 18:00:00', '2026-09-02 08:00:00', '2026-09-10 16:30:00'),
(2, 'Design database normalization schema', 'Create relational tables, foreign keys, and indexes for tasks and users.', 'completed', 'urgent', 2, 1, '2026-09-12 17:00:00', '2026-09-02 09:15:00', '2026-09-11 14:00:00'),
(3, 'Implement JWT authentication API', 'Create login, logout, and token refresh endpoints with proper validation.', 'completed', 'high', 3, 2, '2026-09-15 17:00:00', '2026-09-03 10:00:00', '2026-09-14 11:20:00'),
(4, 'Build task CRUD API endpoints', 'REST endpoints for creating, reading, updating, and deleting tasks.', 'completed', 'high', 3, 2, '2026-09-18 17:00:00', '2026-09-04 11:00:00', '2026-09-17 15:45:00'),
(5, 'Develop secure file upload service', 'MIME type verification, thumbnail generation, and secure storage.', 'in_progress', 'urgent', 4, 1, '2026-10-01 17:00:00', '2026-09-05 09:30:00', '2026-09-20 10:10:00'),
(6, 'Create background job worker queue', 'Process asynchronous emails, image thumbnails, and data export jobs.', 'in_progress', 'high', 5, 2, '2026-10-03 17:00:00', '2026-09-06 14:00:00', '2026-09-22 09:00:00'),
(7, 'Implement chunked large file uploads', 'Support files exceeding 50MB by splitting into sequential chunks.', 'in_progress', 'medium', 4, 1, '2026-10-05 17:00:00', '2026-09-07 10:30:00', '2026-09-21 16:00:00'),
(8, 'Simulate virus scanning engine', 'Pattern matching for EICAR signatures and malicious file headers.', 'in_progress', 'medium', 5, 2, '2026-10-06 17:00:00', '2026-09-08 13:00:00', '2026-09-23 11:15:00'),
(9, 'Build task filter and search frontend', 'Allow filtering by status, priority, assignee, and search query string.', 'pending', 'medium', 3, 2, '2026-10-10 17:00:00', '2026-09-10 09:00:00', '2026-09-10 09:00:00'),
(10, 'Implement drag and drop file upload UI', 'Modern file dropzone with progress bar and thumbnail preview.', 'pending', 'medium', 4, 1, '2026-10-12 17:00:00', '2026-09-11 11:30:00', '2026-09-11 11:30:00'),
(11, 'Add real-time notifications via SSE/WebSocket', 'Broadcast task assignments and status updates to active users.', 'pending', 'high', 3, 1, '2026-10-15 17:00:00', '2026-09-12 15:00:00', '2026-09-12 15:00:00'),
(12, 'Write comprehensive unit and integration tests', 'Test auth flows, task CRUD, file validation, and queue jobs.', 'pending', 'urgent', 5, 2, '2026-10-18 17:00:00', '2026-09-14 08:30:00', '2026-09-14 08:30:00'),
(13, 'Optimize database queries and indexing', 'Profile slow queries and verify indexes on status, priority, and keys.', 'pending', 'low', 2, 1, '2026-10-20 17:00:00', '2026-09-15 14:15:00', '2026-09-15 14:15:00'),
(14, 'Prepare OpenAPI documentation and Postman collection', 'Document all request payloads, responses, headers, and error codes.', 'pending', 'low', 1, 2, '2026-10-22 17:00:00', '2026-09-16 10:00:00', '2026-09-16 10:00:00'),
(15, 'Audit legacy code and remove deprecated endpoints', 'Review code quality, comment cleanliness, and security practices.', 'cancelled', 'low', NULL, 1, '2026-09-30 17:00:00', '2026-09-05 16:00:00', '2026-09-18 13:00:00');

-- 10 Comments
INSERT INTO `task_comments` (`id`, `task_id`, `user_id`, `comment`, `created_at`) VALUES
(1, 1, 1, 'Infrastructure provisioned on target environment with TLS certificates configured.', '2026-09-10 16:25:00'),
(2, 2, 2, 'Foreign key cascade rules have been verified against requirements.', '2026-09-11 13:55:00'),
(3, 3, 3, 'JWT token generation includes user role claim for RBAC validation.', '2026-09-14 11:15:00'),
(4, 4, 3, 'Pagination parameters page and per_page are now supported on task listing.', '2026-09-17 15:40:00'),
(5, 5, 4, 'GD thumbnail generation works for JPEG, PNG, and WebP images.', '2026-09-20 10:05:00'),
(6, 5, 1, 'Please confirm maximum upload filesize limit is enforced at 100MB in php.ini.', '2026-09-20 14:20:00'),
(7, 6, 5, 'Queue worker handles retry logic with exponential backoff on transient errors.', '2026-09-22 08:50:00'),
(8, 7, 4, 'Chunk reassembly logic verifies SHA-256 checksum after all parts are written.', '2026-09-21 15:50:00'),
(9, 8, 5, 'Added regex check for common PHP tag injections in image EXIF blocks.', '2026-09-23 11:10:00'),
(10, 12, 1, 'Ensure test suite covers unauthenticated access rejection with HTTP 401.', '2026-09-24 09:30:00');

-- Sample Task Attachments
INSERT INTO `task_attachments` (`id`, `task_id`, `file_name`, `file_path`, `file_size`, `mime_type`, `thumbnail_path`, `version`, `uploaded_by`, `uploaded_at`) VALUES
(1, 2, 'database_schema_v1.png', 'storage/uploads/database_schema_v1.png', 245760, 'image/png', 'storage/uploads/thumbnails/thumb_database_schema_v1.png', 1, 2, '2026-09-11 13:50:00'),
(2, 5, 'architecture_spec.pdf', 'storage/uploads/architecture_spec.pdf', 1048576, 'application/pdf', NULL, 1, 4, '2026-09-20 09:55:00');
