<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Queue\Jobs\ExportDataJob;
use App\Queue\QueueManager;
use App\Services\TaskService;
use App\Utils\Validator;
use Throwable;

class TaskController
{
    private TaskService $taskService;

    public function __construct(?TaskService $taskService = null)
    {
        $this->taskService = $taskService ?? new TaskService();
    }

    public function index(Request $request): Response
    {
        $page = (int) $request->query('page', 1);
        $perPage = (int) $request->query('per_page', $request->query('limit', 10));
        $sortBy = (string) $request->query('sort_by', 'created_at');
        $sortOrder = (string) $request->query('order', 'desc');

        $filters = [];
        if ($status = $request->query('status')) {
            $filters['status'] = $status;
        }
        if ($priority = $request->query('priority')) {
            $filters['priority'] = $priority;
        }
        if ($assignedUser = $request->query('assigned_user_id')) {
            $filters['assigned_user_id'] = $assignedUser;
        }
        if ($search = $request->query('search')) {
            $filters['search'] = $search;
        }

        $result = $this->taskService->listTasks($filters, $page, $perPage, $sortBy, $sortOrder);
        return Response::json([
            'success' => true,
            'data' => $result['data'],
            'meta' => $result['meta'],
        ]);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->param('id');
        $task = $this->taskService->getTask($id);

        if (!$task) {
            return Response::error("Task #{$id} not found.", 404);
        }

        return Response::success($task);
    }

    public function store(Request $request): Response
    {
        $validation = Validator::validate($request->body(), [
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_user_id' => 'nullable|integer',
            'due_date' => 'nullable|date',
        ]);

        if (!$validation['valid']) {
            return Response::error('Validation failed.', 422, $validation['errors']);
        }

        $creatorId = (int) ($request->user['id'] ?? 1);
        $task = $this->taskService->createTask($validation['data'], $creatorId);

        return Response::success($task, 'Task created successfully.', 201);
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->param('id');

        $validation = Validator::validate($request->body(), [
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_user_id' => 'nullable|integer',
            'due_date' => 'nullable|date',
        ]);

        if (!$validation['valid']) {
            return Response::error('Validation failed.', 422, $validation['errors']);
        }

        try {
            $task = $this->taskService->updateTask($id, $validation['data']);
            return Response::success($task, 'Task updated successfully.');
        } catch (Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 500) ? (int) $e->getCode() : 400;
            return Response::error($e->getMessage(), $code);
        }
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        $deleted = $this->taskService->deleteTask($id);

        if (!$deleted) {
            return Response::error("Task #{$id} not found or already deleted.", 404);
        }

        return Response::success(null, 'Task deleted successfully.');
    }

    public function bulkStatus(Request $request): Response
    {
        $validation = Validator::validate($request->body(), [
            'task_ids' => 'required',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
        ]);

        if (!$validation['valid']) {
            return Response::error('Validation failed.', 422, $validation['errors']);
        }

        $taskIds = $request->input('task_ids');
        if (!is_array($taskIds)) {
            return Response::error('The task_ids parameter must be an array of IDs.', 422);
        }

        $status = (string) $request->input('status');
        $async = (bool) $request->input('async', true);

        $result = $this->taskService->bulkUpdateStatus($taskIds, $status, $async);
        return Response::success($result, 'Bulk task status update initiated.');
    }

    public function export(Request $request): Response
    {
        $format = (string) $request->input('format', 'csv');
        $queueManager = new QueueManager();
        $jobId = $queueManager->push(ExportDataJob::class, ['format' => $format]);

        return Response::success([
            'queued' => true,
            'job_id' => $jobId,
            'format' => $format,
        ], 'Data export job queued.');
    }
}
