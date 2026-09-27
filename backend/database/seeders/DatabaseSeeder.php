<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed 5 Users
        $password = Hash::make('password123');

        $users = [
            User::create([
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'password' => $password,
                'role' => 'admin',
            ]),
            User::create([
                'name' => 'Bob Smith',
                'email' => 'bob@example.com',
                'password' => $password,
                'role' => 'manager',
            ]),
            User::create([
                'name' => 'Charlie Brown',
                'email' => 'charlie@example.com',
                'password' => $password,
                'role' => 'member',
            ]),
            User::create([
                'name' => 'Diana Prince',
                'email' => 'diana@example.com',
                'password' => $password,
                'role' => 'member',
            ]),
            User::create([
                'name' => 'Evan Wright',
                'email' => 'evan@example.com',
                'password' => $password,
                'role' => 'member',
            ]),
        ];

        // 2. Seed 15 Tasks
        $tasksData = [
            [
                'title' => 'Set up production server architecture',
                'description' => 'Provision cloud instances and configure web servers and firewalls.',
                'status' => 'completed',
                'priority' => 'high',
                'assigned_user_id' => $users[0]->id,
                'created_by' => $users[0]->id,
                'due_date' => '2026-09-10 18:00:00',
            ],
            [
                'title' => 'Design database normalization schema',
                'description' => 'Create relational tables, foreign keys, and indexes for tasks and users.',
                'status' => 'completed',
                'priority' => 'urgent',
                'assigned_user_id' => $users[1]->id,
                'created_by' => $users[0]->id,
                'due_date' => '2026-09-12 17:00:00',
            ],
            [
                'title' => 'Implement JWT authentication API',
                'description' => 'Create login, logout, and token refresh endpoints with proper validation.',
                'status' => 'completed',
                'priority' => 'high',
                'assigned_user_id' => $users[2]->id,
                'created_by' => $users[1]->id,
                'due_date' => '2026-09-15 17:00:00',
            ],
            [
                'title' => 'Build task CRUD API endpoints',
                'description' => 'REST endpoints for creating, reading, updating, and deleting tasks.',
                'status' => 'completed',
                'priority' => 'high',
                'assigned_user_id' => $users[2]->id,
                'created_by' => $users[1]->id,
                'due_date' => '2026-09-18 17:00:00',
            ],
            [
                'title' => 'Develop secure file upload service',
                'description' => 'MIME type verification, thumbnail generation, and secure storage.',
                'status' => 'in_progress',
                'priority' => 'urgent',
                'assigned_user_id' => $users[3]->id,
                'created_by' => $users[0]->id,
                'due_date' => '2026-10-01 17:00:00',
            ],
            [
                'title' => 'Create background job worker queue',
                'description' => 'Process asynchronous emails, image thumbnails, and data export jobs.',
                'status' => 'in_progress',
                'priority' => 'high',
                'assigned_user_id' => $users[4]->id,
                'created_by' => $users[1]->id,
                'due_date' => '2026-10-03 17:00:00',
            ],
            [
                'title' => 'Implement chunked large file uploads',
                'description' => 'Support files exceeding 50MB by splitting into sequential chunks.',
                'status' => 'in_progress',
                'priority' => 'medium',
                'assigned_user_id' => $users[3]->id,
                'created_by' => $users[0]->id,
                'due_date' => '2026-10-05 17:00:00',
            ],
            [
                'title' => 'Simulate virus scanning engine',
                'description' => 'Pattern matching for EICAR signatures and malicious file headers.',
                'status' => 'in_progress',
                'priority' => 'medium',
                'assigned_user_id' => $users[4]->id,
                'created_by' => $users[1]->id,
                'due_date' => '2026-10-06 17:00:00',
            ],
            [
                'title' => 'Build task filter and search frontend',
                'description' => 'Allow filtering by status, priority, assignee, and search query string.',
                'status' => 'pending',
                'priority' => 'medium',
                'assigned_user_id' => $users[2]->id,
                'created_by' => $users[1]->id,
                'due_date' => '2026-10-10 17:00:00',
            ],
            [
                'title' => 'Implement drag and drop file upload UI',
                'description' => 'Modern file dropzone with progress bar and thumbnail preview.',
                'status' => 'pending',
                'priority' => 'medium',
                'assigned_user_id' => $users[3]->id,
                'created_by' => $users[0]->id,
                'due_date' => '2026-10-12 17:00:00',
            ],
            [
                'title' => 'Add real-time notifications via SSE/WebSocket',
                'description' => 'Broadcast task assignments and status updates to active users.',
                'status' => 'pending',
                'priority' => 'high',
                'assigned_user_id' => $users[2]->id,
                'created_by' => $users[0]->id,
                'due_date' => '2026-10-15 17:00:00',
            ],
            [
                'title' => 'Write comprehensive unit and integration tests',
                'description' => 'Test auth flows, task CRUD, file validation, and queue jobs.',
                'status' => 'pending',
                'priority' => 'urgent',
                'assigned_user_id' => $users[4]->id,
                'created_by' => $users[1]->id,
                'due_date' => '2026-10-18 17:00:00',
            ],
            [
                'title' => 'Optimize database queries and indexing',
                'description' => 'Profile slow queries and verify indexes on status, priority, and keys.',
                'status' => 'pending',
                'priority' => 'low',
                'assigned_user_id' => $users[1]->id,
                'created_by' => $users[0]->id,
                'due_date' => '2026-10-20 17:00:00',
            ],
            [
                'title' => 'Prepare OpenAPI documentation and Postman collection',
                'description' => 'Document all request payloads, responses, headers, and error codes.',
                'status' => 'pending',
                'priority' => 'low',
                'assigned_user_id' => $users[0]->id,
                'created_by' => $users[1]->id,
                'due_date' => '2026-10-22 17:00:00',
            ],
            [
                'title' => 'Audit legacy code and remove deprecated endpoints',
                'description' => 'Review code quality, comment cleanliness, and security practices.',
                'status' => 'cancelled',
                'priority' => 'low',
                'assigned_user_id' => null,
                'created_by' => $users[0]->id,
                'due_date' => '2026-09-30 17:00:00',
            ],
        ];

        $createdTasks = [];
        foreach ($tasksData as $t) {
            $createdTasks[] = Task::create($t);
        }

        // 3. Seed 10 Comments
        $commentsData = [
            ['task_id' => $createdTasks[0]->id, 'user_id' => $users[0]->id, 'comment' => 'Infrastructure provisioned on target environment with TLS certificates configured.'],
            ['task_id' => $createdTasks[1]->id, 'user_id' => $users[1]->id, 'comment' => 'Foreign key cascade rules have been verified against requirements.'],
            ['task_id' => $createdTasks[2]->id, 'user_id' => $users[2]->id, 'comment' => 'JWT token generation includes user role claim for RBAC validation.'],
            ['task_id' => $createdTasks[3]->id, 'user_id' => $users[2]->id, 'comment' => 'Pagination parameters page and per_page are now supported on task listing.'],
            ['task_id' => $createdTasks[4]->id, 'user_id' => $users[3]->id, 'comment' => 'GD thumbnail generation works for JPEG, PNG, and WebP images.'],
            ['task_id' => $createdTasks[4]->id, 'user_id' => $users[0]->id, 'comment' => 'Please confirm maximum upload filesize limit is enforced at 100MB in php.ini.'],
            ['task_id' => $createdTasks[5]->id, 'user_id' => $users[4]->id, 'comment' => 'Queue worker handles retry logic with exponential backoff on transient errors.'],
            ['task_id' => $createdTasks[6]->id, 'user_id' => $users[3]->id, 'comment' => 'Chunk reassembly logic verifies SHA-256 checksum after all parts are written.'],
            ['task_id' => $createdTasks[7]->id, 'user_id' => $users[4]->id, 'comment' => 'Added regex check for common PHP tag injections in image EXIF blocks.'],
            ['task_id' => $createdTasks[11]->id, 'user_id' => $users[0]->id, 'comment' => 'Ensure test suite covers unauthenticated access rejection with HTTP 401.'],
        ];

        foreach ($commentsData as $c) {
            TaskComment::create($c);
        }

        // 4. Seed Attachments
        TaskAttachment::create([
            'task_id' => $createdTasks[1]->id,
            'file_name' => 'database_schema_v1.png',
            'file_path' => 'storage/uploads/database_schema_v1.png',
            'file_size' => 245760,
            'mime_type' => 'image/png',
            'thumbnail_path' => 'storage/uploads/thumbnails/thumb_database_schema_v1.png',
            'version' => 1,
            'uploaded_by' => $users[1]->id,
        ]);

        TaskAttachment::create([
            'task_id' => $createdTasks[4]->id,
            'file_name' => 'architecture_spec.pdf',
            'file_path' => 'storage/uploads/architecture_spec.pdf',
            'file_size' => 1048576,
            'mime_type' => 'application/pdf',
            'thumbnail_path' => null,
            'version' => 1,
            'uploaded_by' => $users[3]->id,
        ]);
    }
}
