<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class QueueApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
        ]);

        $jwt = new JwtService();
        $this->token = $jwt->generateToken($this->user);
    }

    public function test_can_fetch_queue_stats(): void
    {
        $response = $this->getJson('/api/queue/stats');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'pending' => 0,
                    'failed' => 0,
                ],
            ]);
    }

    public function test_can_queue_and_process_jobs(): void
    {
        $exportRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/tasks/export', ['format' => 'csv']);

        $exportRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'queued' => true,
                ],
            ]);

        // Process queued job via work endpoint
        $workRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/queue/work', ['limit' => 5]);

        $workRes->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }
}
