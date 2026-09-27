<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\BulkTaskStatusUpdateJob;
use App\Jobs\ExportDataJob;
use App\Jobs\SendTaskAssignedEmailJob;
use App\Models\Task;
use App\Services\RealtimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TaskController extends Controller
{
    public function __construct(private RealtimeService $realtime) {}

    private function invalidateTasksCache(): void
    {
        $current = (int) Cache::get('tasks_cache_version', 1);
        Cache::put('tasks_cache_version', $current + 1, now()->addDays(7));
    }

    public function index(Request $request): JsonResponse
    {
        $version = (int) Cache::get('tasks_cache_version', 1);
        $cacheKey = "tasks:index:v{$version}:" . md5($request->fullUrl());

        $cached = Cache::remember($cacheKey, 60, function () use ($request) {
            $query = Task::with(['assignedUser:id,name,email', 'creator:id,name,email'])
                ->withCount(['attachments', 'comments']);

            if ($request->filled('status') && $request->query('status') !== 'all') {
                $query->where('status', $request->query('status'));
            }

            if ($request->filled('priority') && $request->query('priority') !== 'all') {
                $query->where('priority', $request->query('priority'));
            }

            if ($request->filled('assigned_user_id')) {
                $query->where('assigned_user_id', (int) $request->query('assigned_user_id'));
            }

            if ($request->filled('search')) {
                $search = '%' . $request->query('search') . '%';
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', $search)
                      ->orWhere('description', 'like', $search);
                });
            }

            $sortBy = $request->query('sort_by', 'created_at');
            $allowedSorts = ['id', 'title', 'status', 'priority', 'due_date', 'created_at', 'updated_at'];
            $column = in_array(strtolower($sortBy), $allowedSorts, true) ? strtolower($sortBy) : 'created_at';
            $order = strtolower((string) $request->query('order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query->orderBy($column, $order);

            $perPage = min(100, max(1, (int) $request->query('per_page', $request->query('limit', 10))));
            $paginator = $query->paginate($perPage);

            return [
                'data' => $paginator->items(),
                'meta' => [
                    'total' => $paginator->total(),
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ];
        });

        $etag = '"' . md5(json_encode($cached['data']) . $cached['meta']['total']) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response()->json(null, 304, [
                'ETag' => $etag,
                'Cache-Control' => 'private, max-age=60, must-revalidate',
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $cached['data'],
            'meta' => $cached['meta'],
        ])->header('ETag', $etag)
          ->header('Cache-Control', 'private, max-age=60, must-revalidate');
    }

    public function show(int $id): JsonResponse
    {
        $task = Task::with([
            'assignedUser:id,name,email',
            'creator:id,name,email',
            'attachments',
            'comments.user:id,name,email,role',
        ])->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => "Task #{$id} not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $task,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $validated['created_by'] = $request->user()?->id ?? 1;

        $task = Task::create($validated);

        if (!empty($task->assigned_user_id)) {
            SendTaskAssignedEmailJob::dispatch($task->id, (int) $task->assigned_user_id);
        }

        $task->load(['assignedUser:id,name,email', 'creator:id,name,email']);
        $this->invalidateTasksCache();
        $this->realtime->broadcast('task.created', ['task' => $task]);

        return response()->json([
            'success' => true,
            'message' => 'Task created successfully.',
            'data' => $task,
        ], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => "Task #{$id} not found.",
            ], 404);
        }

        $validated = $request->validate([
            'title' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $oldAssigned = $task->assigned_user_id;
        $task->update($validated);

        if (!empty($task->assigned_user_id) && $task->assigned_user_id != $oldAssigned) {
            SendTaskAssignedEmailJob::dispatch($task->id, (int) $task->assigned_user_id);
        }

        $task->load(['assignedUser:id,name,email', 'creator:id,name,email']);
        $this->invalidateTasksCache();
        $this->realtime->broadcast('task.updated', ['task' => $task]);

        return response()->json([
            'success' => true,
            'message' => 'Task updated successfully.',
            'data' => $task,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => "Task #{$id} not found or already deleted.",
            ], 404);
        }

        $task->delete();
        $this->invalidateTasksCache();
        $this->realtime->broadcast('task.deleted', ['task_id' => $id]);

        return response()->json([
            'success' => true,
            'message' => 'Task deleted successfully.',
            'data' => null,
        ]);
    }

    public function bulkStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_ids' => 'required|array',
            'task_ids.*' => 'integer',
            'status' => 'required|in:pending,in_progress,completed,cancelled',
            'async' => 'nullable|boolean',
        ]);

        $async = $request->boolean('async', true);

        if ($async) {
            BulkTaskStatusUpdateJob::dispatch($validated['task_ids'], $validated['status']);
            $this->invalidateTasksCache();
            $this->realtime->broadcast('task.bulk_status', [
                'task_ids' => $validated['task_ids'],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Bulk task status update queued for background processing.',
                'data' => [
                    'queued' => true,
                    'task_ids' => $validated['task_ids'],
                    'status' => $validated['status'],
                ],
            ]);
        }

        $count = Task::whereIn('id', $validated['task_ids'])->update(['status' => $validated['status']]);
        $this->invalidateTasksCache();
        $this->realtime->broadcast('task.bulk_status', [
            'task_ids' => $validated['task_ids'],
            'status' => $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Updated {$count} tasks.",
            'data' => [
                'queued' => false,
                'updated_count' => $count,
            ],
        ]);
    }

    public function export(Request $request): JsonResponse
    {
        $format = $request->input('format', 'csv');
        ExportDataJob::dispatch($format);

        return response()->json([
            'success' => true,
            'message' => 'Data export job queued.',
            'data' => [
                'queued' => true,
                'format' => $format,
            ],
        ]);
    }
}
