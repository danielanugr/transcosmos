<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(int $id): JsonResponse
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => "Task #{$id} not found.",
            ], 404);
        }

        $comments = TaskComment::with('user:id,name,email,role')
            ->where('task_id', $id)
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $comments,
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => "Task #{$id} not found.",
            ], 404);
        }

        $validated = $request->validate([
            'comment' => 'required|string|min:1',
        ]);

        $comment = TaskComment::create([
            'task_id' => $task->id,
            'user_id' => $request->user()?->id ?? 1,
            'comment' => $validated['comment'],
        ]);

        $comment->load('user:id,name,email,role');

        return response()->json([
            'success' => true,
            'message' => 'Comment added successfully.',
            'data' => $comment,
        ], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $comment = TaskComment::find($id);
        if (!$comment) {
            return response()->json([
                'success' => false,
                'error' => "Comment #{$id} not found.",
            ], 404);
        }

        $user = $request->user();
        if ($user && $user->role !== 'admin' && $user->id !== $comment->user_id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized to delete this comment.',
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Comment deleted successfully.',
            'data' => null,
        ]);
    }
}
