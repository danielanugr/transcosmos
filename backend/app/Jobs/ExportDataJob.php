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

class ExportDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $format = 'csv'
    ) {}

    public function handle(): void
    {
        $exportDir = storage_path('app/exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $tasks = Task::with(['assignedUser', 'creator'])->orderBy('id')->get();
        $filename = "task_export_" . date('Ymd_His') . ".{$this->format}";
        $filePath = $exportDir . DIRECTORY_SEPARATOR . $filename;

        if ($this->format === 'csv') {
            $handle = fopen($filePath, 'w');
            fputcsv($handle, [
                'ID', 'Title', 'Description', 'Status', 'Priority',
                'Assigned User', 'Assigned Email', 'Creator', 'Due Date', 'Created At'
            ]);

            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task->id,
                    $task->title,
                    $task->description,
                    $task->status,
                    $task->priority,
                    $task->assignedUser?->name ?? 'Unassigned',
                    $task->assignedUser?->email ?? '',
                    $task->creator?->name,
                    $task->due_date?->toIso8601String(),
                    $task->created_at?->toIso8601String(),
                ]);
            }
            fclose($handle);
        } else {
            $content = "TASK MANAGEMENT PLATFORM - DATA EXPORT REPORT\n";
            $content .= "Generated: " . date('c') . "\n";
            $content .= "Total Records: " . $tasks->count() . "\n\n";

            foreach ($tasks as $task) {
                $content .= sprintf(
                    "[%s] #%d: %s | Status: %s | Priority: %s | Assignee: %s | Due: %s\n",
                    $task->created_at?->toIso8601String(),
                    $task->id,
                    $task->title,
                    $task->status,
                    $task->priority,
                    $task->assignedUser?->name ?? 'Unassigned',
                    $task->due_date?->toIso8601String() ?? 'None'
                );
            }
            file_put_contents($filePath, $content);
        }

        Log::info("Data export job completed", [
            'format' => $this->format,
            'file_name' => $filename,
            'record_count' => $tasks->count(),
            'export_path' => 'storage/app/exports/' . $filename,
        ]);
    }
}
