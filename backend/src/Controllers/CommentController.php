<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Repositories\CommentRepository;
use App\Repositories\TaskRepository;
use App\Utils\Validator;

class CommentController
{
    private CommentRepository $commentRepository;
    private TaskRepository $taskRepository;

    public function __construct(?CommentRepository $commentRepository = null, ?TaskRepository $taskRepository = null)
    {
        $this->commentRepository = $commentRepository ?? new CommentRepository();
        $this->taskRepository = $taskRepository ?? new TaskRepository();
    }

    public function index(Request $request): Response
    {
        $taskId = (int) $request->param('id');
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            return Response::error("Task #{$taskId} not found.", 404);
        }

        $comments = $this->commentRepository->findByTaskId($taskId);
        return Response::success($comments);
    }

    public function store(Request $request): Response
    {
        $taskId = (int) $request->param('id');
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            return Response::error("Task #{$taskId} not found.", 404);
        }

        $validation = Validator::validate($request->body(), [
            'comment' => 'required|string|min:1',
        ]);

        if (!$validation['valid']) {
            return Response::error('Validation failed.', 422, $validation['errors']);
        }

        $userId = (int) ($request->user['id'] ?? 1);
        $comment = $this->commentRepository->create([
            'task_id' => $taskId,
            'user_id' => $userId,
            'comment' => $validation['data']['comment'],
        ]);

        return Response::success($comment, 'Comment added successfully.', 201);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        $comment = $this->commentRepository->findById($id);

        if (!$comment) {
            return Response::error("Comment #{$id} not found.", 404);
        }

        // Only author or admin can delete comment
        $currentUser = $request->user;
        if ($currentUser && $currentUser['role'] !== 'admin' && $currentUser['id'] != $comment['user_id']) {
            return Response::error('Unauthorized to delete this comment.', 403);
        }

        $this->commentRepository->delete($id);
        return Response::success(null, 'Comment deleted successfully.');
    }
}
