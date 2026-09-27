<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TaskAttachment;
use App\Services\ImageThumbnailService;
use App\Services\VirusScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $attachmentId
    ) {}

    public function handle(VirusScannerService $scanner, ImageThumbnailService $thumbnailService): void
    {
        $attachment = TaskAttachment::find($this->attachmentId);
        if (!$attachment) {
            Log::info("Attachment #{$this->attachmentId} was removed before background processing.");
            return;
        }

        $absolutePath = public_path($attachment->file_path);
        if (!file_exists($absolutePath)) {
            $absolutePath = storage_path('app/public/' . str_replace('storage/', '', $attachment->file_path));
        }

        if (!file_exists($absolutePath)) {
            Log::warning("File not found on disk for attachment #{$this->attachmentId}");
            return;
        }

        $scan = $scanner->scanFile($absolutePath);
        if (!$scan['clean']) {
            Log::error("Threat identified during background file processing", [
                'attachment_id' => $this->attachmentId,
                'threat' => $scan['threat'],
            ]);
            throw new RuntimeException("Virus scan failed: " . $scan['threat']);
        }

        if ($thumbnailService->isSupportedImage($attachment->mime_type) && empty($attachment->thumbnail_path)) {
            $thumbName = 'thumb_bg_' . basename($attachment->file_path);
            $thumbDest = storage_path('app/public/uploads/thumbnails/' . $thumbName);
            $generated = $thumbnailService->generate($absolutePath, $thumbDest);
            if ($generated) {
                $attachment->update([
                    'thumbnail_path' => 'storage/uploads/thumbnails/' . $thumbName,
                ]);
            }
        }

        Log::info("Background file processing completed successfully", [
            'attachment_id' => $this->attachmentId,
            'file_name' => $attachment->file_name,
        ]);
    }
}
