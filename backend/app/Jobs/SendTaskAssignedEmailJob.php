<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Task;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendTaskAssignedEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $taskId,
        public int $userId
    ) {}

    public function handle(): void
    {
        $task = Task::find($this->taskId);
        if (!$task) {
            Log::info("Task #{$this->taskId} was deleted before notification could be sent. Skipping.");
            return;
        }

        $user = User::find($this->userId);
        if (!$user) {
            Log::info("User #{$this->userId} not found for task notification. Skipping.");
            return;
        }

        Log::info("Dispatched task assignment notification email", [
            'to' => $user->email,
            'recipient_name' => $user->name,
            'subject' => "New Task Assigned: {$task->title}",
            'task_id' => $task->id,
            'priority' => $task->priority,
            'due_date' => $task->due_date?->toIso8601String(),
            'dispatched_at' => now()->toIso8601String(),
        ]);
    }
}
