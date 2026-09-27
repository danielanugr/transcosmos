<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AttachmentRepository;
use RuntimeException;

class FileUploadService
{
    private AttachmentRepository $attachmentRepository;
    private VirusScannerService $virusScanner;
    private ImageThumbnailService $thumbnailService;

    public function __construct(
        ?AttachmentRepository $attachmentRepository = null,
        ?VirusScannerService $virusScanner = null,
        ?ImageThumbnailService $thumbnailService = null
    ) {
        $this->attachmentRepository = $attachmentRepository ?? new AttachmentRepository();
        $this->virusScanner = $virusScanner ?? new VirusScannerService();
        $this->thumbnailService = $thumbnailService ?? new ImageThumbnailService();
    }

    public function uploadAttachment(int $taskId, array $fileData, ?int $uploadedBy = null): array
    {
        $this->validateUploadData($fileData);

        $tempPath = $fileData['tmp_name'];
        $originalName = basename($fileData['name']);
        $fileSize = (int) $fileData['size'];

        // Detect real MIME type via finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tempPath) ?: 'application/octet-stream';
        finfo_close($finfo);

        $allowedMimes = config('app.upload.allowed_mimes', []);
        if (!in_array($mimeType, $allowedMimes, true)) {
            throw new RuntimeException("Unsupported file type: {$mimeType}. Allowed formats include images, documents, and videos.", 422);
        }

        // Virus scanning simulation
        $scanResult = $this->virusScanner->scanFile($tempPath);
        if (!$scanResult['clean']) {
            throw new RuntimeException("Security violation: " . $scanResult['threat'], 422);
        }

        // Generate safe unique filename
        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        $uniqueName = bin2hex(random_bytes(16)) . ($ext ? ".{$ext}" : '');

        $uploadDir = config('app.upload.storage_path', storage_path('uploads'));
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . DIRECTORY_SEPARATOR . $uniqueName;
        if (!move_uploaded_file($tempPath, $destPath) && !rename($tempPath, $destPath)) {
            throw new RuntimeException('Failed to store uploaded file on server.', 500);
        }

        // Generate thumbnail if image
        $thumbnailPath = null;
        if ($this->thumbnailService->isSupportedImage($mimeType)) {
            $thumbDir = config('app.upload.thumbnail_path', storage_path('uploads/thumbnails'));
            $thumbName = 'thumb_' . $uniqueName;
            $thumbDest = $thumbDir . DIRECTORY_SEPARATOR . $thumbName;
            $generated = $this->thumbnailService->generate($destPath, $thumbDest);
            if ($generated) {
                $thumbnailPath = 'storage/uploads/thumbnails/' . $thumbName;
            }
        }

        // File versioning calculation
        $latestVersion = $this->attachmentRepository->getLatestVersion($taskId, $originalName);
        $version = $latestVersion + 1;

        $record = [
            'task_id' => $taskId,
            'file_name' => $originalName,
            'file_path' => 'storage/uploads/' . $uniqueName,
            'file_size' => $fileSize,
            'mime_type' => $mimeType,
            'thumbnail_path' => $thumbnailPath,
            'version' => $version,
            'uploaded_by' => $uploadedBy,
        ];

        return $this->attachmentRepository->create($record);
    }

    public function handleChunk(
        int $taskId,
        string $uploadId,
        string $fileName,
        int $chunkIndex,
        int $totalChunks,
        string $chunkTempPath,
        ?int $uploadedBy = null
    ): array {
        if ($totalChunks <= 0 || $chunkIndex < 0 || $chunkIndex >= $totalChunks) {
            throw new RuntimeException('Invalid chunk index or total chunks parameter.', 400);
        }

        $tempBase = config('app.upload.temp_path', storage_path('temp'));
        $uploadTempDir = $tempBase . DIRECTORY_SEPARATOR . preg_replace('/[^a-zA-Z0-9_-]/', '', $uploadId);
        if (!is_dir($uploadTempDir)) {
            mkdir($uploadTempDir, 0755, true);
        }

        $chunkDest = $uploadTempDir . DIRECTORY_SEPARATOR . sprintf('chunk_%05d', $chunkIndex);
        if (!move_uploaded_file($chunkTempPath, $chunkDest) && !rename($chunkTempPath, $chunkDest)) {
            throw new RuntimeException('Failed to save file chunk.', 500);
        }

        $chunkSize = filesize($chunkDest);
        $this->attachmentRepository->saveChunk([
            'upload_id' => $uploadId,
            'task_id' => $taskId,
            'file_name' => $fileName,
            'chunk_index' => $chunkIndex,
            'total_chunks' => $totalChunks,
            'chunk_size' => $chunkSize,
            'chunk_path' => $chunkDest,
        ]);

        $storedChunks = $this->attachmentRepository->getChunks($uploadId);
        if (count($storedChunks) < $totalChunks) {
            return [
                'complete' => false,
                'upload_id' => $uploadId,
                'received_chunks' => count($storedChunks),
                'total_chunks' => $totalChunks,
            ];
        }

        // All chunks received: Assemble into final file
        $combinedTemp = $uploadTempDir . DIRECTORY_SEPARATOR . 'assembled_' . bin2hex(random_bytes(8));
        $out = fopen($combinedTemp, 'wb');
        if (!$out) {
            throw new RuntimeException('Could not create destination stream for chunk reassembly.', 500);
        }

        foreach ($storedChunks as $chunk) {
            $in = fopen($chunk['chunk_path'], 'rb');
            if ($in) {
                while (!feof($in)) {
                    $buf = fread($in, 65536);
                    if ($buf !== false) {
                        fwrite($out, $buf);
                    }
                }
                fclose($in);
                @unlink($chunk['chunk_path']);
            }
        }
        fclose($out);

        $this->attachmentRepository->clearChunks($uploadId);
        @rmdir($uploadTempDir);

        // Upload assembled file using standard pipeline
        $pseudoFile = [
            'name' => $fileName,
            'tmp_name' => $combinedTemp,
            'size' => filesize($combinedTemp),
            'error' => UPLOAD_ERR_OK,
        ];

        try {
            $attachment = $this->uploadAttachment($taskId, $pseudoFile, $uploadedBy);
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

    private function validateUploadData(array $fileData): void
    {
        if (!isset($fileData['error']) || is_array($fileData['error'])) {
            throw new RuntimeException('Invalid file upload parameters.', 400);
        }

        switch ($fileData['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Exceeded file size limit.', 422);
            case UPLOAD_ERR_PARTIAL:
                throw new RuntimeException('File was only partially uploaded.', 400);
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file was uploaded.', 400);
            default:
                throw new RuntimeException('Unknown upload error.', 500);
        }

        $maxSize = (int) config('app.upload.max_file_size', 52428800);
        if ($fileData['size'] > $maxSize) {
            throw new RuntimeException("File exceeds maximum allowed size of " . ($maxSize / 1048576) . "MB.", 422);
        }
    }
}
