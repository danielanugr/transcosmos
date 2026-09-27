<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FileChunk;
use App\Models\TaskAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class FileUploadService
{
    private VirusScannerService $virusScanner;
    private ImageThumbnailService $thumbnailService;

    private array $allowedMimes = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain', 'text/csv',
        'video/mp4', 'video/webm', 'video/ogg',
    ];

    public function __construct(
        ?VirusScannerService $virusScanner = null,
        ?ImageThumbnailService $thumbnailService = null
    ) {
        $this->virusScanner = $virusScanner ?? new VirusScannerService();
        $this->thumbnailService = $thumbnailService ?? new ImageThumbnailService();
    }

    public function uploadAttachment(int $taskId, UploadedFile $file, ?int $uploadedBy = null): TaskAttachment
    {
        $realPath = $file->getRealPath();
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = $realPath ? finfo_file($finfo, $realPath) : null;
        finfo_close($finfo);

        $mimeType = ($detectedMime && $detectedMime !== 'application/x-empty')
            ? $detectedMime
            : ($file->getMimeType() ?: 'application/octet-stream');

        if (!in_array($mimeType, $this->allowedMimes, true)) {
            throw new RuntimeException("Unsupported file type: {$mimeType}. Allowed formats include images, documents, and videos.", 422);
        }

        $scan = $this->virusScanner->scanFile($realPath);
        if (!$scan['clean']) {
            throw new RuntimeException("Security violation: " . $scan['threat'], 422);
        }

        $ext = $file->getClientOriginalExtension();
        $uniqueName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');
        $storedRelPath = $file->storeAs('uploads', $uniqueName, 'public');
        $absolutePath = storage_path('app/public/' . $storedRelPath);

        $thumbnailPath = null;
        if ($this->thumbnailService->isSupportedImage($mimeType)) {
            $thumbName = 'thumb_' . $uniqueName;
            $thumbDest = storage_path('app/public/uploads/thumbnails/' . $thumbName);
            $generated = $this->thumbnailService->generate($absolutePath, $thumbDest);
            if ($generated) {
                $thumbnailPath = 'storage/uploads/thumbnails/' . $thumbName;
            }
        }

        $latestVersion = TaskAttachment::where('task_id', $taskId)
            ->where('file_name', $originalName)
            ->max('version') ?? 0;

        return TaskAttachment::create([
            'task_id' => $taskId,
            'file_name' => $originalName,
            'file_path' => 'storage/' . $storedRelPath,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'thumbnail_path' => $thumbnailPath,
            'version' => $latestVersion + 1,
            'uploaded_by' => $uploadedBy,
            'uploaded_at' => now(),
        ]);
    }

    public function handleChunk(
        int $taskId,
        string $uploadId,
        string $fileName,
        int $chunkIndex,
        int $totalChunks,
        UploadedFile $chunkFile,
        ?int $uploadedBy = null
    ): array {
        if ($totalChunks <= 0 || $chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            throw new RuntimeException('Invalid chunk index or total chunks parameter.', 400);
        }

        $tempDir = storage_path('app/temp/' . preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId));
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $chunkDest = $tempDir . DIRECTORY_SEPARATOR . sprintf('chunk_%05d', $chunkIndex);
        $chunkFile->move($tempDir, sprintf('chunk_%05d', $chunkIndex));

        FileChunk::create([
            'upload_id' => $uploadId,
            'task_id' => $taskId,
            'file_name' => $fileName,
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'chunk_size' => filesize($chunkDest),
            'chunk_path' => $chunkDest,
        ]);

        $storedChunks = FileChunk::where('upload_id', $uploadId)->orderBy('chunk_index')->get();
        if ($storedChunks->count() < $totalChunks) {
            return [
                'complete' => false,
                'upload_id' => $uploadId,
                'received_chunks' => $storedChunks->count(),
                'total_chunks' => $totalChunks,
            ];
        }

        $combinedTemp = $tempDir . DIRECTORY_SEPARATOR . 'assembled_' . bin2hex(random_bytes(8));
        $out = fopen($combinedTemp, 'wb');

        foreach ($storedChunks as $c) {
            $in = fopen($c->chunk_path, 'rb');
            if ($in) {
                while (!feof($in)) {
                    $buf = fread($in, 65536);
                    if ($buf !== false) {
                        fwrite($out, $buf);
                    }
                }
                fclose($in);
                @unlink($c->chunk_path);
            }
        }
        fclose($out);

        FileChunk::where('upload_id', $uploadId)->delete();
        @rmdir($tempDir);

        $uploadedFile = new UploadedFile(
            $combinedTemp,
            $fileName,
            null,
            null,
            true
        );

        try {
            $attachment = $this->uploadAttachment($taskId, $uploadedFile, $uploadedBy);
            @unlink($combinedTemp);
            return [
                'complete' => true,
                'attachment' => $attachment,
            ];
        } catch (\Throwable $e) {
            @unlink($combinedTemp);
            throw $e;
        }
    }
}
