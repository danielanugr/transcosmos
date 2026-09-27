<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BonusFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create([
            'email' => 'alice@example.com',
            'role' => 'admin',
        ]);
        $jwt = new JwtService();
        $this->token = $jwt->generateToken($this->user);
    }

    public function test_video_streaming_supports_full_and_range_requests(): void
    {
        Storage::fake('public');
        $videoContent = str_repeat('VIDEODATA1234567890', 100);
        $file = UploadedFile::fake()->createWithContent('demo.mp4', $videoContent);

        $task = Task::create([
            'title' => 'Video Task',
            'created_by' => $this->user->id,
        ]);

        $storedPath = $file->storeAs('uploads', 'test_video.mp4', 'public');
        $attachment = TaskAttachment::create([
            'task_id' => $task->id,
            'file_name' => 'demo.mp4',
            'file_path' => 'storage/' . $storedPath,
            'file_size' => strlen($videoContent),
            'mime_type' => 'video/mp4',
            'uploaded_at' => now(),
        ]);

        // Full content request
        $res = $this->get("/api/attachments/{$attachment->id}/stream");
        $res->assertStatus(200);
        $res->assertHeader('Accept-Ranges', 'bytes');
        $res->assertHeader('Content-Type', 'video/mp4');

        // Partial range request (HTTP 206)
        $rangeRes = $this->withHeader('Range', 'bytes=0-99')
            ->get("/api/attachments/{$attachment->id}/stream");

        $rangeRes->assertStatus(206);
        $rangeRes->assertHeader('Content-Range', "bytes 0-99/" . strlen($videoContent));
        $rangeRes->assertHeader('Content-Length', '100');
    }

    public function test_realtime_presence_and_typing_indicators(): void
    {
        $task = Task::create([
            'title' => 'Presence Task',
            'created_by' => $this->user->id,
        ]);

        // Post presence
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/realtime/presence', [
                'task_id' => $task->id,
            ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);

        // Get presence list
        $listRes = $this->getJson('/api/realtime/presence');
        $listRes->assertStatus(200);
        $this->assertNotEmpty($listRes->json('data'));

        // Post typing indicator
        $typingRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/realtime/typing', [
                'task_id' => $task->id,
                'is_typing' => true,
            ]);

        $typingRes->assertStatus(200);
        $typingRes->assertJsonPath('success', true);

        // Test SSE polling endpoint for events
        $streamPoll = $this->getJson('/api/realtime/stream?poll=1');
        $streamPoll->assertStatus(200);
        $streamPoll->assertJsonPath('success', true);
    }

    public function test_task_etag_caching_and_invalidation(): void
    {
        Task::create([
            'title' => 'Cached Task 1',
            'created_by' => $this->user->id,
        ]);

        $firstRes = $this->getJson('/api/tasks');
        $firstRes->assertStatus(200);
        $etag = $firstRes->headers->get('ETag');
        $this->assertNotEmpty($etag);

        // Matching ETag returns 304 Not Modified
        $secondRes = $this->withHeader('If-None-Match', $etag)
            ->getJson('/api/tasks');
        $secondRes->assertStatus(304);

        // Mutating task creates new version
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/tasks', [
                'title' => 'New Invalidation Task',
            ]);
        $createRes->assertStatus(201);

        // Previous ETag no longer matches 304, returns 200 with new data
        $thirdRes = $this->withHeader('If-None-Match', $etag)
            ->getJson('/api/tasks');
        $thirdRes->assertStatus(200);
    }
}
