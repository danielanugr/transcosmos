<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\BulkTaskStatusUpdateJob;
use App\Jobs\SendTaskAssignedEmailJob;
use App\Models\Task;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TaskApiTest extends TestCase
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

    public function test_can_list_tasks_with_pagination(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Task::create([
                'title' => "Task Number {$i}",
                'description' => "Description for task {$i}",
                'status' => 'pending',
                'priority' => 'medium',
                'created_by' => $this->user->id,
            ]);
        }

        $response = $this->getJson('/api/tasks?page=1&per_page=5');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total' => 15,
                    'page' => 1,
                    'per_page' => 5,
                    'last_page' => 3,
                ],
            ]);

        $this->assertCount(5, $response->json('data'));
    }

    public function test_can_filter_tasks_by_status(): void
    {
        Task::create([
            'title' => 'Completed Task',
            'status' => 'completed',
            'priority' => 'low',
            'created_by' => $this->user->id,
        ]);

        Task::create([
            'title' => 'Pending Task',
            'status' => 'pending',
            'priority' => 'high',
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/tasks?status=completed');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Completed Task', $response->json('data.0.title'));
    }

    public function test_can_create_task_and_dispatch_assignment_job(): void
    {
        Queue::fake();

        $assignee = User::create([
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
            'password' => Hash::make('password123'),
            'role' => 'member',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/tasks', [
                'title' => 'New Assigned Task',
                'description' => 'Important details',
                'priority' => 'urgent',
                'status' => 'pending',
                'assigned_user_id' => $assignee->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'New Assigned Task',
                    'priority' => 'urgent',
                    'assigned_user_id' => $assignee->id,
                ],
            ]);

        Queue::assertPushed(SendTaskAssignedEmailJob::class, function ($job) use ($assignee) {
            return $job->userId === $assignee->id;
        });
    }

    public function test_can_update_task(): void
    {
        $task = Task::create([
            'title' => 'Initial Title',
            'status' => 'pending',
            'priority' => 'low',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/tasks/{$task->id}", [
                'title' => 'Updated Title',
                'status' => 'in_progress',
                'priority' => 'high',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'title' => 'Updated Title',
                    'status' => 'in_progress',
                    'priority' => 'high',
                ],
            ]);
    }

    public function test_can_delete_task(): void
    {
        $task = Task::create([
            'title' => 'Task to Delete',
            'status' => 'pending',
            'priority' => 'low',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_can_bulk_update_task_statuses(): void
    {
        Queue::fake();

        $task1 = Task::create(['title' => 'T1', 'created_by' => $this->user->id]);
        $task2 = Task::create(['title' => 'T2', 'created_by' => $this->user->id]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/tasks/bulk-status', [
                'task_ids' => [$task1->id, $task2->id],
                'status' => 'completed',
                'async' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'queued' => true,
                ],
            ]);

        Queue::assertPushed(BulkTaskStatusUpdateJob::class);
    }
}
