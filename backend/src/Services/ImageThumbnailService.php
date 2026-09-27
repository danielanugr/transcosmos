<?php

declare(strict_types=1);

namespace App\Services;

class ImageThumbnailService
{
    private int $maxWidth;
    private int $maxHeight;

    public function __construct(int $maxWidth = 200, int $maxHeight = 200)
    {
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
    }

    public function isSupportedImage(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
        ], true);
    }

    public function generate(string $sourcePath, string $destinationPath): ?string
    {
        if (!extension_loaded('gd') || !file_exists($sourcePath)) {
            return null;
        }

        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            return null;
        }

        [$origWidth, $origHeight, $imageType] = $imageInfo;
        if ($origWidth <= 0 || $origHeight <= 0) {
            return null;
        }

        $ratio = min($this->maxWidth / $origWidth, $this->maxHeight / $origHeight);
        $newWidth = (int) max(1, round($origWidth * $ratio));
        $newHeight = (int) max(1, round($origHeight * $ratio));

        $srcImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$srcImage) {
            return null;
        }

        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        if (!$destImage) {
            imagedestroy($srcImage);
            return null;
        }

        if (in_array($imageType, [IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        }

        imagecopyresampled($destImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $destDir = dirname($destinationPath);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $saved = match ($imageType) {
            IMAGETYPE_JPEG => imagejpeg($destImage, $destinationPath, 85),
            IMAGETYPE_PNG => imagepng($destImage, $destinationPath, 8),
            IMAGETYPE_GIF => imagegif($destImage, $destinationPath),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($destImage, $destinationPath, 85) : false,
            default => false,
        };

        imagedestroy($srcImage);
        imagedestroy($destImage);

        return $saved ? $destinationPath : null;
    }
}
