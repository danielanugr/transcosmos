<?php

declare(strict_types=1);

namespace Tests;

use App\Queue\QueueManager;
use App\Queue\Jobs\SendTaskAssignedEmailJob;
use App\Queue\Jobs\BulkTaskStatusUpdateJob;
use App\Queue\Jobs\ExportDataJob;
use App\Repositories\JobRepository;
use App\Repositories\TaskRepository;

class QueueTest
{
    private QueueManager $queueManager;
    private JobRepository $jobRepository;
    private TaskRepository $taskRepository;

    public function __construct()
    {
        $this->queueManager = new QueueManager();
        $this->jobRepository = new JobRepository();
        $this->taskRepository = new TaskRepository();
    }

    public function run(): void
    {
        echo "Running Queue Tests...\n";
        $this->testJobPushAndReservation();
        $this->testTaskAssignedEmailJobExecution();
        $this->testBulkStatusUpdateJobExecution();
        $this->testExportDataJobExecution();
        echo " -> Queue Tests Passed (4/4)\n\n";
    }

    private function testJobPushAndReservation(): void
    {
        // Drain any pending jobs from earlier test suites
        while ($this->queueManager->processNextJob() !== null) {
            // clear
        }

        $jobId = $this->queueManager->push(SendTaskAssignedEmailJob::class, [
            'task_id' => 1,
            'user_id' => 1,
        ]);

        assert($jobId > 0, 'Pushing job must return a positive job ID.');

        $result = $this->queueManager->processNextJob();
        assert($result !== null, 'Queue processor should pick up the pending job.');
        assert($result['success'] === true, 'Valid job must execute with success.');
        assert($result['job_id'] === $jobId, 'Processed job ID must match pushed job ID.');
    }

    private function testTaskAssignedEmailJobExecution(): void
    {
        $this->queueManager->push(SendTaskAssignedEmailJob::class, [
            'task_id' => 2,
            'user_id' => 2,
        ]);

        $result = $this->queueManager->processNextJob();
        assert($result['success'] === true, 'Task assignment email job must complete without error.');
    }

    private function testBulkStatusUpdateJobExecution(): void
    {
        // Pick task 9 and 10
        $this->queueManager->push(BulkTaskStatusUpdateJob::class, [
            'task_ids' => [9, 10],
            'status' => 'completed',
        ]);

        $result = $this->queueManager->processNextJob();
        assert($result['success'] === true, 'Bulk status update job should succeed.');

        $task9 = $this->taskRepository->findById(9);
        $task10 = $this->taskRepository->findById(10);
        assert($task9['status'] === 'completed', 'Task 9 status should be updated to completed.');
        assert($task10['status'] === 'completed', 'Task 10 status should be updated to completed.');
    }

    private function testExportDataJobExecution(): void
    {
        $this->queueManager->push(ExportDataJob::class, [
            'format' => 'csv',
        ]);

        $result = $this->queueManager->processNextJob();
        assert($result['success'] === true, 'Export data job should succeed.');

        $exports = glob(storage_path('exports/*.csv'));
        assert(!empty($exports), 'At least one exported CSV file should exist in storage/exports/.');
    }
}
