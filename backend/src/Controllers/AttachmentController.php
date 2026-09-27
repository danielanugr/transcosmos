<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Queue\Jobs\ProcessFileJob;
use App\Queue\QueueManager;
use App\Repositories\AttachmentRepository;
use App\Repositories\TaskRepository;
use App\Services\FileUploadService;
use Throwable;

class AttachmentController
{
    private FileUploadService $uploadService;
    private AttachmentRepository $attachmentRepository;
    private TaskRepository $taskRepository;
    private QueueManager $queueManager;

    public function __construct(
        ?FileUploadService $uploadService = null,
        ?AttachmentRepository $attachmentRepository = null,
        ?TaskRepository $taskRepository = null,
        ?QueueManager $queueManager = null
    ) {
        $this->uploadService = $uploadService ?? new FileUploadService();
        $this->attachmentRepository = $attachmentRepository ?? new AttachmentRepository();
        $this->taskRepository = $taskRepository ?? new TaskRepository();
        $this->queueManager = $queueManager ?? new QueueManager();
    }

    public function upload(Request $request): Response
    {
        $taskId = (int) $request->param('id');
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            return Response::error("Task #{$taskId} not found.", 404);
        }

        $file = $request->file('file') ?? $request->file('attachment');
        if (!$file) {
            return Response::error('No file payload detected in request.', 400);
        }

        try {
            $userId = !empty($request->user['id']) ? (int) $request->user['id'] : null;
            $attachment = $this->uploadService->uploadAttachment($taskId, $file, $userId);

            // Trigger background file processing job
            $this->queueManager->push(ProcessFileJob::class, [
                'attachment_id' => $attachment['id'],
            ]);

            return Response::success($attachment, 'File uploaded successfully.', 201);
        } catch (Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 500) ? (int) $e->getCode() : 422;
            return Response::error($e->getMessage(), $code);
        }
    }

    public function uploadChunk(Request $request): Response
    {
        $taskId = (int) $request->param('id');
        $task = $this->taskRepository->findById($taskId);
        if (!$task) {
            return Response::error("Task #{$taskId} not found.", 404);
        }

        $uploadId = (string) $request->input('upload_id');
        $fileName = (string) $request->input('file_name');
        $chunkIndex = (int) $request->input('chunk_index', -1);
        $totalChunks = (int) $request->input('total_chunks', 0);

        $file = $request->file('chunk') ?? $request->file('file');
        if (!$file || empty($file['tmp_name'])) {
            return Response::error('Chunk payload is required.', 400);
        }

        if (empty($uploadId) || empty($fileName) || $chunkIndex < 0 || $totalChunks <= 0) {
            return Response::error('Missing chunk metadata (upload_id, file_name, chunk_index, total_chunks).', 422);
        }

        try {
            $userId = !empty($request->user['id']) ? (int) $request->user['id'] : null;
            $result = $this->uploadService->handleChunk(
                $taskId,
                $uploadId,
                $fileName,
                $chunkIndex,
                $totalChunks,
                $file['tmp_name'],
                $userId
            );

            if ($result['complete'] && isset($result['attachment']['id'])) {
                $this->queueManager->push(ProcessFileJob::class, [
                    'attachment_id' => $result['attachment']['id'],
                ]);
            }

            return Response::success($result, $result['complete'] ? 'File upload complete.' : 'Chunk processed.');
        } catch (Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 500) ? (int) $e->getCode() : 500;
            return Response::error($e->getMessage(), $code);
        }
    }

    public function download(Request $request): Response
    {
        $id = (int) $request->param('id');
        $attachment = $this->attachmentRepository->findById($id);

        if (!$attachment) {
            return Response::error("Attachment #{$id} not found.", 404);
        }

        $absolutePath = base_path($attachment['file_path']);
        if (!file_exists($absolutePath)) {
            return Response::error('Underlying file does not exist on server storage.', 404);
        }

        return Response::download($absolutePath, $attachment['file_name'], $attachment['mime_type']);
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->param('id');
        $attachment = $this->attachmentRepository->findById($id);

        if (!$attachment) {
            return Response::error("Attachment #{$id} not found.", 404);
        }

        $filePath = base_path($attachment['file_path']);
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        if (!empty($attachment['thumbnail_path'])) {
            $thumbPath = base_path($attachment['thumbnail_path']);
            if (file_exists($thumbPath)) {
                @unlink($thumbPath);
            }
        }

        $this->attachmentRepository->delete($id);
        return Response::success(null, 'Attachment deleted successfully.');
    }
}
