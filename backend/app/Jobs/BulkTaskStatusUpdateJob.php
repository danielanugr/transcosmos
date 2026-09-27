<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Task;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BulkTaskStatusUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public array $taskIds,
        public string $status
    ) {}

    public function handle(): void
    {
        $updatedCount = Task::whereIn('id', $this->taskIds)->update([
            'status' => $this->status,
        ]);

        Log::info("Completed bulk task status update job", [
            'target_status' => $this->status,
            'updated_count' => $updatedCount,
            'task_ids' => $this->taskIds,
        ]);
    }
}
