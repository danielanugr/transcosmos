<?php

declare(strict_types=1);

namespace App\Services;

use App\Queue\QueueManager;
use App\Queue\Jobs\SendTaskAssignedEmailJob;
use App\Queue\Jobs\BulkTaskStatusUpdateJob;
use App\Repositories\TaskRepository;
use App\Repositories\AttachmentRepository;
use App\Repositories\CommentRepository;
use RuntimeException;

class TaskService
{
    private TaskRepository $taskRepository;
    private AttachmentRepository $attachmentRepository;
    private CommentRepository $commentRepository;
    private QueueManager $queueManager;

    public function __construct(
        ?TaskRepository $taskRepository = null,
        ?AttachmentRepository $attachmentRepository = null,
        ?CommentRepository $commentRepository = null,
        ?QueueManager $queueManager = null
    ) {
        $this->taskRepository = $taskRepository ?? new TaskRepository();
        $this->attachmentRepository = $attachmentRepository ?? new AttachmentRepository();
        $this->commentRepository = $commentRepository ?? new CommentRepository();
        $this->queueManager = $queueManager ?? new QueueManager();
    }

    public function listTasks(array $filters = [], int $page = 1, int $perPage = 10, string $sortBy = 'created_at', string $sortOrder = 'DESC'): array
    {
        return $this->taskRepository->findPaginated($filters, $page, $perPage, $sortBy, $sortOrder);
    }

    public function getTask(int $id): ?array
    {
        $task = $this->taskRepository->findById($id);
        if (!$task) {
            return null;
        }

        $task['attachments'] = $this->attachmentRepository->findByTaskId($id);
        $task['comments'] = $this->commentRepository->findByTaskId($id);

        return $task;
    }

    public function createTask(array $data, int $creatorId): array
    {
        $data['created_by'] = $creatorId;
        $task = $this->taskRepository->create($data);

        // Queue notification email if assigned user is set
        if (!empty($task['assigned_user_id'])) {
            $this->queueManager->push(SendTaskAssignedEmailJob::class, [
                'task_id' => $task['id'],
                'user_id' => $task['assigned_user_id'],
            ]);
        }

        return $task;
    }

    public function updateTask(int $id, array $data): ?array
    {
        $existing = $this->taskRepository->findById($id);
        if (!$existing) {
            throw new RuntimeException("Task #{$id} not found.", 404);
        }

        $oldAssigned = $existing['assigned_user_id'];
        $updated = $this->taskRepository->update($id, $data);

        // If assignment changed, queue notification email for new assignee
        if (!empty($updated['assigned_user_id']) && $updated['assigned_user_id'] != $oldAssigned) {
            $this->queueManager->push(SendTaskAssignedEmailJob::class, [
                'task_id' => $updated['id'],
                'user_id' => $updated['assigned_user_id'],
            ]);
        }

        return $updated;
    }

    public function deleteTask(int $id): bool
    {
        $attachments = $this->attachmentRepository->findByTaskId($id);
        foreach ($attachments as $att) {
            $file = base_path($att['file_path']);
            if (file_exists($file)) {
                @unlink($file);
            }
            if (!empty($att['thumbnail_path'])) {
                $thumb = base_path($att['thumbnail_path']);
                if (file_exists($thumb)) {
                    @unlink($thumb);
                }
            }
        }

        return $this->taskRepository->delete($id);
    }

    public function bulkUpdateStatus(array $taskIds, string $status, bool $async = true): array
    {
        if ($async) {
            $jobId = $this->queueManager->push(BulkTaskStatusUpdateJob::class, [
                'task_ids' => $taskIds,
                'status' => $status,
            ]);

            return [
                'queued' => true,
                'job_id' => $jobId,
                'message' => 'Bulk task status update queued for background processing.',
            ];
        }

        $count = $this->taskRepository->bulkUpdateStatus($taskIds, $status);
        return [
            'queued' => false,
            'updated_count' => $count,
        ];
    }
}
