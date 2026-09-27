<?php

declare(strict_types=1);

namespace App\Queue\Jobs;

use App\Queue\JobInterface;
use App\Repositories\TaskRepository;
use App\Utils\Logger;
use RuntimeException;

class BulkTaskStatusUpdateJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $taskIds = $payload['task_ids'] ?? [];
        $status = $payload['status'] ?? '';

        if (empty($taskIds) || !is_array($taskIds)) {
            throw new RuntimeException('No task IDs provided for bulk status update.');
        }

        $validStatuses = ['pending', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses, true)) {
            throw new RuntimeException("Invalid target status '{$status}' for bulk update.");
        }

        $taskRepo = new TaskRepository();
        $updatedCount = $taskRepo->bulkUpdateStatus($taskIds, $status);

        Logger::info("Completed bulk task status update job", [
            'target_status' => $status,
            'updated_count' => $updatedCount,
            'task_ids' => $taskIds,
        ]);
    }
}
