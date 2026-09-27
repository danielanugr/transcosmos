<?php

declare(strict_types=1);

namespace Tests;

use App\Services\TaskService;
use App\Repositories\TaskRepository;

class TaskTest
{
    private TaskService $taskService;
    private TaskRepository $taskRepository;

    public function __construct()
    {
        $this->taskService = new TaskService();
        $this->taskRepository = new TaskRepository();
    }

    public function run(): void
    {
        echo "Running Task Tests...\n";
        $this->testPagination();
        $this->testStatusFilter();
        $this->testSearchFilter();
        $this->testCreateReadUpdateDeleteCycle();
        $this->testBulkStatusUpdate();
        echo " -> Task Tests Passed (5/5)\n\n";
    }

    private function testPagination(): void
    {
        $result = $this->taskService->listTasks([], 1, 5);

        assert(count($result['data']) === 5, 'Page limit 5 should return exactly 5 tasks.');
        assert($result['meta']['page'] === 1, 'Current page should be 1.');
        assert($result['meta']['per_page'] === 5, 'Per page should be 5.');
        assert($result['meta']['total'] >= 15, 'Total should reflect at least 15 seeded tasks.');
    }

    private function testStatusFilter(): void
    {
        $result = $this->taskService->listTasks(['status' => 'completed'], 1, 20);

        assert(count($result['data']) > 0, 'Should find completed tasks.');
        foreach ($result['data'] as $task) {
            assert($task['status'] === 'completed', 'All returned tasks must have status completed.');
        }
    }

    private function testSearchFilter(): void
    {
        $result = $this->taskService->listTasks(['search' => 'normalization'], 1, 10);

        assert(count($result['data']) >= 1, 'Should find task matching search term.');
        assert(str_contains(strtolower($result['data'][0]['title']), 'normalization'), 'Matched task title must match search.');
    }

    private function testCreateReadUpdateDeleteCycle(): void
    {
        // 1. Create
        $newTask = $this->taskService->createTask([
            'title' => 'Automated Integration Test Task',
            'description' => 'Temporary task created during test execution.',
            'status' => 'pending',
            'priority' => 'urgent',
            'assigned_user_id' => 3,
            'due_date' => '2026-11-01 12:00:00',
        ], 1);

        assert(!empty($newTask['id']), 'Created task must return an ID.');
        assert($newTask['title'] === 'Automated Integration Test Task', 'Task title must match input.');
        assert($newTask['created_by'] == 1, 'Creator ID must match.');

        $taskId = (int) $newTask['id'];

        // 2. Read
        $fetched = $this->taskService->getTask($taskId);
        assert($fetched !== null, 'Created task should be fetchable.');
        assert($fetched['priority'] === 'urgent', 'Task priority must match.');
        assert(isset($fetched['attachments']), 'Task should have attachments array.');
        assert(isset($fetched['comments']), 'Task should have comments array.');

        // 3. Update
        $updated = $this->taskService->updateTask($taskId, [
            'status' => 'in_progress',
            'priority' => 'high',
        ]);
        assert($updated['status'] === 'in_progress', 'Updated status should be in_progress.');
        assert($updated['priority'] === 'high', 'Updated priority should be high.');

        // 4. Delete
        $deleted = $this->taskService->deleteTask($taskId);
        assert($deleted === true, 'Task deletion should return true.');

        $afterDelete = $this->taskService->getTask($taskId);
        assert($afterDelete === null, 'Deleted task should no longer exist.');
    }

    private function testBulkStatusUpdate(): void
    {
        // Direct synchronous bulk update verification
        $result = $this->taskService->bulkUpdateStatus([1, 2], 'completed', false);
        assert($result['queued'] === false, 'Synchronous update should not be queued.');

        // Asynchronous queue job dispatch verification
        $asyncResult = $this->taskService->bulkUpdateStatus([1, 2], 'in_progress', true);
        assert($asyncResult['queued'] === true, 'Asynchronous update should be queued.');
        assert(!empty($asyncResult['job_id']), 'Queue job ID should be returned.');
    }
}
