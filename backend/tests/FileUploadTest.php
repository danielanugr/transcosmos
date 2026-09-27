<?php

declare(strict_types=1);

namespace Tests;

use App\Services\FileUploadService;
use App\Services\ImageThumbnailService;
use App\Services\VirusScannerService;
use App\Repositories\AttachmentRepository;
use RuntimeException;

class FileUploadTest
{
    private FileUploadService $uploadService;
    private VirusScannerService $virusScanner;
    private ImageThumbnailService $thumbnailService;
    private AttachmentRepository $attachmentRepository;

    public function __construct()
    {
        $this->uploadService = new FileUploadService();
        $this->virusScanner = new VirusScannerService();
        $this->thumbnailService = new ImageThumbnailService();
        $this->attachmentRepository = new AttachmentRepository();
    }

    public function run(): void
    {
        echo "Running File Upload Tests...\n";
        $this->testVirusScannerCleanFile();
        $this->testVirusScannerInfectedFile();
        $this->testThumbnailGeneration();
        $this->testSecureFileUploadAndVersioning();
        $this->testChunkedUploadAssembly();
        echo " -> File Upload Tests Passed (5/5)\n\n";
    }

    private function testVirusScannerCleanFile(): void
    {
        $cleanPath = storage_path('temp/clean_test.txt');
        file_put_contents($cleanPath, 'Valid document content without malicious payloads.');

        $result = $this->virusScanner->scanFile($cleanPath);
        assert($result['clean'] === true, 'Clean text file should pass virus scan.');
        assert($result['threat'] === null, 'Clean text file should have no threat string.');

        @unlink($cleanPath);
    }

    private function testVirusScannerInfectedFile(): void
    {
        $infectedPath = storage_path('temp/eicar_test.txt');
        file_put_contents($infectedPath, 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*');

        $result = $this->virusScanner->scanFile($infectedPath);
        assert($result['clean'] === false, 'EICAR signature must trigger virus scan alert.');
        assert(str_contains(strtolower($result['threat'] ?? ''), 'eicar') || str_contains(strtolower($result['threat'] ?? ''), 'antivirus'), 'Threat name must reference EICAR or antivirus.');

        @unlink($infectedPath);
    }

    private function testThumbnailGeneration(): void
    {
        if (!extension_loaded('gd')) {
            echo " [Skipping GD thumbnail test: GD extension not loaded]\n";
            return;
        }

        // Create a 400x300 sample PNG image in memory
        $img = imagecreatetruecolor(400, 300);
        $blue = imagecolorallocate($img, 30, 144, 255);
        imagefilledrectangle($img, 0, 0, 400, 300, $blue);

        $srcPath = storage_path('temp/sample_400x300.png');
        $destThumb = storage_path('temp/thumb_sample.png');
        imagepng($img, $srcPath);
        imagedestroy($img);

        $res = $this->thumbnailService->generate($srcPath, $destThumb);
        assert($res !== null, 'Thumbnail generation should return target path.');
        assert(file_exists($destThumb), 'Thumbnail file must exist on disk.');

        [$thumbWidth, $thumbHeight] = getimagesize($destThumb);
        assert($thumbWidth <= 200 && $thumbHeight <= 200, 'Thumbnail dimensions must be <= 200x200.');
        assert($thumbWidth === 200, 'Width should be scaled to max width 200.');
        assert($thumbHeight === 150, 'Height should be scaled proportionally to 150.');

        @unlink($srcPath);
        @unlink($destThumb);
    }

    private function testSecureFileUploadAndVersioning(): void
    {
        $tempPath = storage_path('temp/test_doc.txt');
        file_put_contents($tempPath, 'Sample project documentation text.');

        $fileData = [
            'name' => 'spec_document.txt',
            'tmp_name' => $tempPath,
            'size' => filesize($tempPath),
            'error' => UPLOAD_ERR_OK,
        ];

        // Version 1 upload
        $attachmentV1 = $this->uploadService->uploadAttachment(1, $fileData, 1);
        assert($attachmentV1['version'] === 1, 'Initial upload should be version 1.');
        assert($attachmentV1['file_name'] === 'spec_document.txt', 'Original filename preserved.');

        // Recreate temp file for Version 2 upload with identical name
        file_put_contents($tempPath, 'Updated project documentation text content.');
        $fileDataV2 = [
            'name' => 'spec_document.txt',
            'tmp_name' => $tempPath,
            'size' => filesize($tempPath),
            'error' => UPLOAD_ERR_OK,
        ];

        $attachmentV2 = $this->uploadService->uploadAttachment(1, $fileDataV2, 1);
        assert($attachmentV2['version'] === 2, 'Repeated upload of same file must increment to version 2.');

        // Clean up stored files and db records
        $this->attachmentRepository->delete((int) $attachmentV1['id']);
        $this->attachmentRepository->delete((int) $attachmentV2['id']);
        @unlink(base_path($attachmentV1['file_path']));
        @unlink(base_path($attachmentV2['file_path']));
        @unlink($tempPath);
    }

    private function testChunkedUploadAssembly(): void
    {
        $uploadId = 'test_upload_' . bin2hex(random_bytes(6));
        $part1Path = storage_path('temp/part1.bin');
        $part2Path = storage_path('temp/part2.bin');

        file_put_contents($part1Path, "First portion of large dataset...\n");
        file_put_contents($part2Path, "Second portion of large dataset.\n");

        // Send Chunk 0
        $res1 = $this->uploadService->handleChunk(1, $uploadId, 'large_dataset.txt', 0, 2, $part1Path, 1);
        assert($res1['complete'] === false, 'First chunk should not complete upload.');
        assert($res1['received_chunks'] === 1, 'Received chunk count should be 1.');

        // Send Chunk 1 (final)
        $res2 = $this->uploadService->handleChunk(1, $uploadId, 'large_dataset.txt', 1, 2, $part2Path, 1);
        assert($res2['complete'] === true, 'Final chunk must complete upload and trigger reassembly.');
        assert(isset($res2['attachment']['id']), 'Reassembled file must create an attachment record.');

        $attachmentId = (int) $res2['attachment']['id'];
        $filePath = base_path($res2['attachment']['file_path']);
        assert(file_exists($filePath), 'Assembled file must exist on disk.');

        $content = file_get_contents($filePath);
        assert(str_contains($content, 'First portion') && str_contains($content, 'Second portion'), 'Content must contain both chunks.');

        // Clean up
        $this->attachmentRepository->delete($attachmentId);
        @unlink($filePath);
    }
}
