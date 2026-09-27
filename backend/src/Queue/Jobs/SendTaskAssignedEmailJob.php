<?php

declare(strict_types=1);

namespace App\Queue\Jobs;

use App\Queue\JobInterface;
use App\Repositories\UserRepository;
use App\Repositories\TaskRepository;
use App\Utils\Logger;
use RuntimeException;

class SendTaskAssignedEmailJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $taskId = (int) ($payload['task_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);

        $taskRepo = new TaskRepository();
        $userRepo = new UserRepository();

        $task = $taskRepo->findById($taskId);
        if (!$task) {
            Logger::info("Task #{$taskId} was deleted before assignment notification was sent. Skipping job.");
            return;
        }

        $user = $userRepo->findById($userId);
        if (!$user) {
            Logger::info("User #{$userId} was deleted before assignment notification was sent. Skipping job.");
            return;
        }

        // Email dispatch simulation
        $emailLog = [
            'to' => $user['email'],
            'recipient_name' => $user['name'],
            'subject' => "New Task Assigned: {$task['title']}",
            'task_id' => $taskId,
            'priority' => $task['priority'],
            'due_date' => $task['due_date'],
            'dispatched_at' => date('c'),
        ];

        Logger::info("Dispatched task assignment notification email", $emailLog);
    }
}
