-- Task Management Platform Complete Database Dump
-- Schema and Sample Seed Data
-- Target: MySQL 8.0+ / MariaDB 10.4+

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `file_chunks`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `task_comments`;
DROP TABLE IF EXISTS `task_attachments`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `users`;

SET FOREIGN_KEY_CHECKS = 1;

-- Users table
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'manager', 'member') NOT NULL DEFAULT 'member',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tasks table
CREATE TABLE `tasks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `priority` ENUM('low', 'medium', 'high', 'urgent') NOT NULL DEFAULT 'medium',
    `assigned_user_id` BIGINT UNSIGNED NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `due_date` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_tasks_status` (`status`),
    KEY `idx_tasks_priority` (`priority`),
    KEY `idx_tasks_assigned_user` (`assigned_user_id`),
    KEY `idx_tasks_created_by` (`created_by`),
    KEY `idx_tasks_due_date` (`due_date`),
    KEY `idx_tasks_created_at` (`created_at`),
    CONSTRAINT `fk_tasks_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task attachments table
CREATE TABLE `task_attachments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` BIGINT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` BIGINT UNSIGNED NOT NULL,
    `mime_type` VARCHAR(100) NOT NULL,
    `thumbnail_path` VARCHAR(500) NULL,
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `uploaded_by` BIGINT UNSIGNED NULL,
    `uploaded_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_attachments_task_id` (`task_id`),
    KEY `idx_attachments_version` (`task_id`, `file_name`, `version`),
    KEY `idx_attachments_uploaded_by` (`uploaded_by`),
    CONSTRAINT `fk_attachments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Task comments table
CREATE TABLE `task_comments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `task_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `comment` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_comments_task_id` (`task_id`),
    KEY `idx_comments_user_id` (`user_id`),
    KEY `idx_comments_created_at` (`created_at`),
    CONSTRAINT `fk_comments_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Background queue jobs table
CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(50) NOT NULL DEFAULT 'default',
    `job_class` VARCHAR(150) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    `error_message` TEXT NULL,
    `reserved_at` TIMESTAMP NULL,
    `available_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jobs_queue_status` (`queue`, `status`, `available_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chunked file uploads temporary state table
CREATE TABLE `file_chunks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `upload_id` VARCHAR(64) NOT NULL,
    `task_id` BIGINT UNSIGNED NOT NULL,
    `file_name` VARCHAR(255) NOT NULL,
    `chunk_index` INT UNSIGNED NOT NULL,
    `total_chunks` INT UNSIGNED NOT NULL,
    `chunk_size` BIGINT UNSIGNED NOT NULL,
    `chunk_path` VARCHAR(500) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_upload_chunk` (`upload_id`, `chunk_index`),
    KEY `idx_chunks_upload_id` (`upload_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Sample Data (Default password for all users is: password123)
-- ============================================================

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'Alice Johnson', 'alice@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'admin', '2026-09-01 08:00:00', '2026-09-01 08:00:00'),
(2, 'Bob Smith', 'bob@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'manager', '2026-09-01 08:30:00', '2026-09-01 08:30:00'),
(3, 'Charlie Brown', 'charlie@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'member', '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
(4, 'Diana Prince', 'diana@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'member', '2026-09-01 09:30:00', '2026-09-01 09:30:00'),
(5, 'Evan Wright', 'evan@example.com', '$2y$10$dMy9J1BuaVR8Id08UH1TKu962nId4Q7pUR2X8e700rN7S0nXj.cNC', 'member', '2026-09-01 10:00:00', '2026-09-01 10:00:00');

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

INSERT INTO `task_attachments` (`id`, `task_id`, `file_name`, `file_path`, `file_size`, `mime_type`, `thumbnail_path`, `version`, `uploaded_by`, `uploaded_at`) VALUES
(1, 2, 'database_schema_v1.png', 'storage/uploads/database_schema_v1.png', 245760, 'image/png', 'storage/uploads/thumbnails/thumb_database_schema_v1.png', 1, 2, '2026-09-11 13:50:00'),
(2, 5, 'architecture_spec.pdf', 'storage/uploads/architecture_spec.pdf', 1048576, 'application/pdf', NULL, 1, 4, '2026-09-20 09:55:00');
