<?php

declare(strict_types=1);

namespace App\Queue\Jobs;

use App\Queue\JobInterface;
use App\Repositories\TaskRepository;
use App\Utils\Logger;

class ExportDataJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $format = strtolower($payload['format'] ?? 'csv');
        $exportDir = storage_path('exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $taskRepo = new TaskRepository();
        $tasksResult = $taskRepo->findPaginated([], 1, 1000);
        $tasks = $tasksResult['data'];

        $timestamp = date('Ymd_His');
        $filename = "task_export_{$timestamp}.{$format}";
        $filePath = $exportDir . DIRECTORY_SEPARATOR . $filename;

        if ($format === 'csv') {
            $handle = fopen($filePath, 'w');
            fputcsv($handle, [
                'ID', 'Title', 'Description', 'Status', 'Priority',
                'Assigned User', 'Assigned Email', 'Creator', 'Due Date', 'Created At'
            ]);

            foreach ($tasks as $task) {
                fputcsv($handle, [
                    $task['id'],
                    $task['title'],
                    $task['description'],
                    $task['status'],
                    $task['priority'],
                    $task['assigned_user_name'] ?? 'Unassigned',
                    $task['assigned_user_email'] ?? '',
                    $task['creator_name'],
                    $task['due_date'],
                    $task['created_at'],
                ]);
            }
            fclose($handle);
        } else {
            // Textual report format for documentation or PDF printer
            $content = "TASK MANAGEMENT PLATFORM - DATA EXPORT REPORT\n";
            $content .= "Generated: " . date('c') . "\n";
            $content .= "Total Records: " . count($tasks) . "\n\n";
            $content .= str_repeat("=", 80) . "\n";

            foreach ($tasks as $task) {
                $content .= sprintf(
                    "[%s] #%d: %s | Status: %s | Priority: %s | Assignee: %s | Due: %s\n",
                    $task['created_at'],
                    $task['id'],
                    $task['title'],
                    $task['status'],
                    $task['priority'],
                    $task['assigned_user_name'] ?? 'Unassigned',
                    $task['due_date'] ?? 'None'
                );
            }
            file_put_contents($filePath, $content);
        }

        Logger::info("Data export job completed", [
            'format' => $format,
            'file_name' => $filename,
            'record_count' => count($tasks),
            'export_path' => 'storage/exports/' . $filename,
        ]);
    }
}
