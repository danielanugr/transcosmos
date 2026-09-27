<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ImageThumbnailService;
use Tests\TestCase;

class ImageThumbnailServiceTest extends TestCase
{
    private ImageThumbnailService $service;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ImageThumbnailService(200, 200);
        $this->tempDir = sys_get_temp_dir() . '/thumb_test_' . bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            foreach ($files as $f) {
                @unlink($f);
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_supported_image_mimes(): void
    {
        $this->assertTrue($this->service->isSupportedImage('image/jpeg'));
        $this->assertTrue($this->service->isSupportedImage('image/png'));
        $this->assertTrue($this->service->isSupportedImage('image/gif'));
        $this->assertTrue($this->service->isSupportedImage('image/webp'));
        $this->assertFalse($this->service->isSupportedImage('application/pdf'));
        $this->assertFalse($this->service->isSupportedImage('video/mp4'));
    }

    public function test_supported_video_mimes(): void
    {
        $this->assertTrue($this->service->isSupportedVideo('video/mp4'));
        $this->assertTrue($this->service->isSupportedVideo('video/webm'));
        $this->assertTrue($this->service->isSupportedVideo('video/ogg'));
        $this->assertFalse($this->service->isSupportedVideo('image/png'));
    }

    public function test_generates_video_thumbnail_with_valid_gd_canvas(): void
    {
        $destination = $this->tempDir . '/video_preview.jpg';

        $result = $this->service->generateVideoThumbnail($destination, 'MP4');

        $this->assertNotNull($result);
        $this->assertFileExists($destination);

        $imageSize = getimagesize($destination);
        $this->assertNotFalse($imageSize);
        $this->assertSame(320, $imageSize[0]);
        $this->assertSame(180, $imageSize[1]);
    }

    public function test_generates_png_thumbnail_maintaining_dimensions(): void
    {
        $source = $this->tempDir . '/test_source.png';
        $dest = $this->tempDir . '/test_thumb.png';

        $img = imagecreatetruecolor(400, 200);
        $color = imagecolorallocate($img, 100, 150, 200);
        imagefilledrectangle($img, 0, 0, 400, 200, $color);
        imagepng($img, $source);
        imagedestroy($img);

        $result = $this->service->generate($source, $dest);

        $this->assertNotNull($result);
        $this->assertFileExists($dest);

        $size = getimagesize($dest);
        $this->assertNotFalse($size);
        $this->assertSame(200, $size[0]);
        $this->assertSame(100, $size[1]);
    }

    public function test_returns_null_for_invalid_image(): void
    {
        $source = $this->tempDir . '/corrupt.jpg';
        file_put_contents($source, 'NOT_AN_IMAGE_FILE');
        $dest = $this->tempDir . '/should_not_exist.jpg';

        $result = $this->service->generate($source, $dest);

        $this->assertNull($result);
        $this->assertFileDoesNotExist($dest);
    }
}
