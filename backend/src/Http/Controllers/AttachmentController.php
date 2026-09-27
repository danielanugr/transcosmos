<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\ProcessFileJob;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class AttachmentController extends Controller
{
    public function __construct(private FileUploadService $uploadService) {}

    private function resolveAttachmentPath(TaskAttachment $attachment): ?string
    {
        $disk = Storage::disk('public');
        $relPath = str_replace('storage/', '', $attachment->file_path);

        if ($disk->exists($relPath)) {
            return $disk->path($relPath);
        }

        $path = public_path($attachment->file_path);
        if (file_exists($path)) {
            return $path;
        }

        $storagePath = storage_path('app/public/' . $relPath);
        if (file_exists($storagePath)) {
            return $storagePath;
        }

        return null;
    }

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

        $path = $this->resolveAttachmentPath($attachment);
        if (!$path || !file_exists($path)) {
            return response()->json([
                'success' => false,
                'error' => 'Underlying file does not exist on storage.',
            ], 404);
        }

        return response()->download($path, $attachment->file_name, [
            'Content-Type' => $attachment->mime_type,
        ]);
    }

    public function stream(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $attachment = TaskAttachment::find($id);
        if (!$attachment) {
            return response()->json([
                'success' => false,
                'error' => "Attachment #{$id} not found.",
            ], 404);
        }

        $path = $this->resolveAttachmentPath($attachment);
        if (!$path || !file_exists($path)) {
            return response()->json([
                'success' => false,
                'error' => 'Underlying video file does not exist on storage.',
            ], 404);
        }

        $fileSize = (int) filesize($path);
        $start = 0;
        $end = $fileSize - 1;
        $status = 200;

        $headers = [
            'Content-Type' => $attachment->mime_type,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => 'inline; filename="' . basename($attachment->file_name) . '"',
        ];

        $range = $request->header('Range');
        if ($range && preg_match('/bytes=(\d+)-(\d*)/i', $range, $matches)) {
            $start = (int) $matches[1];
            if (!empty($matches[2])) {
                $end = min((int) $matches[2], $fileSize - 1);
            }

            if ($start > $end || $start >= $fileSize) {
                return response()->make('', 416, [
                    'Content-Range' => "bytes */{$fileSize}",
                ]);
            }

            $length = $end - $start + 1;
            $status = 206;
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
            $headers['Content-Length'] = (string) $length;
        } else {
            $length = $fileSize;
            $headers['Content-Length'] = (string) $fileSize;
        }

        return response()->stream(function () use ($path, $start, $length) {
            $stream = fopen($path, 'rb');
            if ($stream === false) {
                return;
            }

            fseek($stream, $start);
            $buffer = 128 * 1024;
            $bytesRemaining = $length;

            while (!feof($stream) && $bytesRemaining > 0 && connection_status() === CONNECTION_NORMAL) {
                $readSize = min($bytesRemaining, $buffer);
                $chunk = fread($stream, $readSize);
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                flush();
                $bytesRemaining -= strlen($chunk);
            }

            fclose($stream);
        }, $status, $headers);
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
