<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessFileJob;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class AttachmentController extends Controller
{
    public function __construct(private FileUploadService $uploadService) {}

    public function upload(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => "Task #{$id} not found.",
            ], 404);
        }

        $file = $request->file('file') ?? $request->file('attachment');
        if (!$file) {
            return response()->json([
                'success' => false,
                'error' => 'No file payload detected in request.',
            ], 400);
        }

        try {
            $userId = $request->user()?->id;
            $attachment = $this->uploadService->uploadAttachment($task->id, $file, $userId);

            // Queue post-processing
            ProcessFileJob::dispatch($attachment->id);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully.',
                'data' => $attachment,
            ], 201);
        } catch (Throwable $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 500) ? (int) $e->getCode() : 422;
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        }
    }

    public function uploadChunk(Request $request, int $id): JsonResponse
    {
        $task = Task::find($id);
        if (!$task) {
            return response()->json([
                'success' => false,
                'error' => "Task #{$id} not found.",
            ], 404);
        }

        $validated = $request->validate([
            'upload_id' => 'required|string',
            'file_name' => 'required|string',
            'chunk_index' => 'required|integer|min:0',
            'total_chunks' => 'required|integer|min:1',
            'chunk' => 'nullable|file',
            'file' => 'nullable|file',
        ]);

        $chunkFile = $request->file('chunk') ?? $request->file('file');
        if (!$chunkFile) {
            return response()->json([
                'success' => false,
                'error' => 'Chunk payload is required.',
            ], 400);
        }

        try {
            $userId = $request->user()?->id;
            $result = $this->uploadService->handleChunk(
                $task->id,
                $validated['upload_id'],
                $validated['file_name'],
                (int) $validated['chunk_index'],
                (int) $validated['total_chunks'],
                $chunkFile,
                $userId
            );

            if ($result['complete'] && isset($result['attachment']->id)) {
                ProcessFileJob::dispatch($result['attachment']->id);
            }

            return response()->json([
                'success' => true,
                'message' => $result['complete'] ? 'File upload complete.' : 'Chunk processed.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            $status = ($e->getCode() >= 400 && $e->getCode() < 500) ? (int) $e->getCode() : 500;
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], $status);
        }
    }

    public function download(int $id): BinaryFileResponse|JsonResponse
    {
        $attachment = TaskAttachment::find($id);
        if (!$attachment) {
            return response()->json([
                'success' => false,
                'error' => "Attachment #{$id} not found.",
            ], 404);
        }

        $path = public_path($attachment->file_path);
        if (!file_exists($path)) {
            $path = storage_path('app/public/' . str_replace('storage/', '', $attachment->file_path));
        }

        if (!file_exists($path)) {
            return response()->json([
                'success' => false,
                'error' => 'Underlying file does not exist on storage.',
            ], 404);
        }

        return response()->download($path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $attachment = TaskAttachment::find($id);
        if (!$attachment) {
            return response()->json([
                'success' => false,
                'error' => "Attachment #{$id} not found.",
            ], 404);
        }

        $path = public_path($attachment->file_path);
        if (!file_exists($path)) {
            $path = storage_path('app/public/' . str_replace('storage/', '', $attachment->file_path));
        }

        if (file_exists($path)) {
            @unlink($path);
        }

        if ($attachment->thumbnail_path) {
            $thumb = public_path($attachment->thumbnail_path);
            if (!file_exists($thumb)) {
                $thumb = storage_path('app/public/' . str_replace('storage/', '', $attachment->thumbnail_path));
            }
            if (file_exists($thumb)) {
                @unlink($thumb);
            }
        }

        $attachment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attachment deleted successfully.',
            'data' => null,
        ]);
    }
}
