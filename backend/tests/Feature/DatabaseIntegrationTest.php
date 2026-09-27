<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FileChunk;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Database Test User',
            'email' => 'db_user@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_task_relationships_load_properly(): void
    {
        $task = Task::create([
            'title' => 'Integration Task',
            'description' => 'Testing model relationships',
            'status' => 'pending',
            'priority' => 'high',
            'created_by' => $this->user->id,
            'assigned_user_id' => $this->user->id,
        ]);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'comment' => 'First test comment',
        ]);

        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'file_name' => 'report.pdf',
            'file_path' => 'storage/uploads/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'version' => 1,
            'uploaded_by' => $this->user->id,
            'uploaded_at' => now(),
        ]);

        $loaded = Task::with(['creator', 'assignedUser', 'comments', 'attachments'])->find($task->id);

        $this->assertNotNull($loaded);
        $this->assertSame($this->user->id, $loaded->creator->id);
        $this->assertSame($this->user->id, $loaded->assignedUser->id);
        $this->assertCount(1, $loaded->comments);
        $this->assertSame($comment->id, $loaded->comments->first()->id);
        $this->assertCount(1, $loaded->attachments);
        $this->assertSame($attachment->id, $loaded->attachments->first()->id);
    }

    public function test_deleting_task_cascades_comments_and_attachments(): void
    {
        $task = Task::create([
            'title' => 'Task To Delete',
            'description' => 'Will be purged',
            'status' => 'pending',
            'priority' => 'low',
            'created_by' => $this->user->id,
        ]);

        TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $this->user->id,
            'comment' => 'Comment to cascade',
        ]);

        TaskAttachment::create([
            'task_id' => $task->id,
            'file_name' => 'cascade.png',
            'file_path' => 'storage/uploads/cascade.png',
            'file_size' => 2048,
            'mime_type' => 'image/png',
            'version' => 1,
            'uploaded_by' => $this->user->id,
            'uploaded_at' => now(),
        ]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id]);
        $this->assertDatabaseHas('task_comments', ['task_id' => $task->id]);
        $this->assertDatabaseHas('task_attachments', ['task_id' => $task->id]);

        $task->delete();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
        $this->assertDatabaseMissing('task_comments', ['task_id' => $task->id]);
        $this->assertDatabaseMissing('task_attachments', ['task_id' => $task->id]);
    }

    public function test_file_chunk_table_lifecycle(): void
    {
        $uploadId = 'chunk_test_upload_' . bin2hex(random_bytes(4));
        $task = Task::create([
            'title' => 'Chunk Task',
            'status' => 'pending',
            'priority' => 'medium',
            'created_by' => $this->user->id,
        ]);

        for ($i = 0; $i < 3; $i++) {
            FileChunk::create([
                'upload_id' => $uploadId,
                'task_id' => $task->id,
                'file_name' => 'large_video.mp4',
                'chunk_index' => $i,
                'total_chunks' => 3,
                'chunk_size' => 1048576,
                'chunk_path' => "/tmp/{$uploadId}_chunk_{$i}",
            ]);
        }

        $chunks = FileChunk::where('upload_id', $uploadId)->orderBy('chunk_index')->get();
        $this->assertCount(3, $chunks);
        $this->assertSame(0, $chunks[0]->chunk_index);
        $this->assertSame(2, $chunks[2]->chunk_index);

        FileChunk::where('upload_id', $uploadId)->delete();
        $this->assertSame(0, FileChunk::where('upload_id', $uploadId)->count());
    }
}
