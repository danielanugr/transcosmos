<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->user = User::create([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $this->task = Task::create([
            'title' => 'Sample Task for Attachments',
            'created_by' => $this->user->id,
        ]);

        $jwt = new JwtService();
        $this->token = $jwt->generateToken($this->user);
    }

    public function test_can_upload_valid_attachment(): void
    {
        $file = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/tasks/{$this->task->id}/attachments", [
                'file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'file_name' => 'document.pdf',
                    'version' => 1,
                ],
            ]);

        $this->assertDatabaseHas('task_attachments', [
            'task_id' => $this->task->id,
            'file_name' => 'document.pdf',
            'version' => 1,
        ]);
    }

    public function test_version_increments_when_uploading_same_filename(): void
    {
        $file1 = UploadedFile::fake()->create('report.txt', 100, 'text/plain');
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/tasks/{$this->task->id}/attachments", ['file' => $file1]);

        $file2 = UploadedFile::fake()->create('report.txt', 150, 'text/plain');
        $response2 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/tasks/{$this->task->id}/attachments", ['file' => $file2]);

        $response2->assertStatus(201);
        $this->assertEquals(2, $response2->json('data.version'));
    }

    public function test_can_handle_chunked_upload_and_assembly(): void
    {
        $uploadId = 'chunk_test_' . bin2hex(random_bytes(4));
        $chunk1 = UploadedFile::fake()->createWithContent('chunk_0.part', 'Part 1 of file');
        $chunk2 = UploadedFile::fake()->createWithContent('chunk_1.part', 'Part 2 of file');

        // Chunk 0
        $res1 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/tasks/{$this->task->id}/attachments/chunk", [
                'upload_id' => $uploadId,
                'file_name' => 'assembled_dataset.txt',
                'chunk_index' => 0,
                'total_chunks' => 2,
                'chunk' => $chunk1,
            ]);

        $res1->assertStatus(200)
            ->assertJson([
                'data' => [
                    'complete' => false,
                    'received_chunks' => 1,
                ],
            ]);

        // Chunk 1 (final)
        $res2 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/tasks/{$this->task->id}/attachments/chunk", [
                'upload_id' => $uploadId,
                'file_name' => 'assembled_dataset.txt',
                'chunk_index' => 1,
                'total_chunks' => 2,
                'chunk' => $chunk2,
            ]);

        $res2->assertStatus(200)
            ->assertJson([
                'data' => [
                    'complete' => true,
                ],
            ]);

        $this->assertDatabaseHas('task_attachments', [
            'task_id' => $this->task->id,
            'file_name' => 'assembled_dataset.txt',
        ]);
    }

    public function test_can_delete_attachment(): void
    {
        $file = UploadedFile::fake()->create('temp_delete.txt', 50, 'text/plain');
        $uploadRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->post("/api/tasks/{$this->task->id}/attachments", ['file' => $file]);

        $attachmentId = $uploadRes->json('data.id');

        $delRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/attachments/{$attachmentId}");

        $delRes->assertStatus(200);
        $this->assertDatabaseMissing('task_attachments', ['id' => $attachmentId]);
    }
}
