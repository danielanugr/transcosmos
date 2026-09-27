<?php

declare(strict_types=1);

namespace App\Queue\Jobs;

use App\Database\Database;
use App\Queue\JobInterface;
use App\Repositories\AttachmentRepository;
use App\Services\ImageThumbnailService;
use App\Services\VirusScannerService;
use App\Utils\Logger;
use RuntimeException;

class ProcessFileJob implements JobInterface
{
    public function handle(array $payload): void
    {
        $attachmentId = (int) ($payload['attachment_id'] ?? 0);
        $attachmentRepo = new AttachmentRepository();
        $attachment = $attachmentRepo->findById($attachmentId);

        if (!$attachment) {
            throw new RuntimeException("Attachment #{$attachmentId} not found for background processing.");
        }

        $absolutePath = base_path($attachment['file_path']);
        if (!file_exists($absolutePath)) {
            throw new RuntimeException("Underlying file not found at {$absolutePath}");
        }

        // 1. Virus scan simulation
        $scanner = new VirusScannerService();
        $scan = $scanner->scanFile($absolutePath);
        if (!$scan['clean']) {
            Logger::error("Threat identified during background file processing", [
                'attachment_id' => $attachmentId,
                'threat' => $scan['threat'],
            ]);
            throw new RuntimeException("Virus scan failed: " . $scan['threat']);
        }

        // 2. Thumbnail generation if image and thumbnail not yet created
        $thumbnailService = new ImageThumbnailService();
        if ($thumbnailService->isSupportedImage($attachment['mime_type']) && empty($attachment['thumbnail_path'])) {
            $thumbDir = storage_path('uploads/thumbnails');
            $thumbName = 'thumb_bg_' . basename($attachment['file_path']);
            $thumbDest = $thumbDir . DIRECTORY_SEPARATOR . $thumbName;

            $generated = $thumbnailService->generate($absolutePath, $thumbDest);
            if ($generated) {
                $relativeThumb = 'storage/uploads/thumbnails/' . $thumbName;
                $pdo = Database::getConnection();
                $stmt = $pdo->prepare('UPDATE task_attachments SET thumbnail_path = :thumb WHERE id = :id');
                $stmt->execute(['thumb' => $relativeThumb, 'id' => $attachmentId]);
            }
        }

        Logger::info("Background file processing completed successfully", [
            'attachment_id' => $attachmentId,
            'file_name' => $attachment['file_name'],
        ]);
    }
}
