<?php

declare(strict_types=1);

namespace App\Http;

class Response
{
    private int $statusCode;
    private array $headers;
    private string $content;
    private ?string $filePath = null;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public static function json(mixed $data, int $statusCode = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json; charset=utf-8';
        $content = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return new self($content ?: '{}', $statusCode, $headers);
    }

    public static function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): self
    {
        return self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, mixed $details = null): self
    {
        $payload = [
            'success' => false,
            'error' => $message,
        ];

        if ($details !== null) {
            $payload['details'] = $details;
        }

        return self::json($payload, $statusCode);
    }

    public static function download(string $filePath, ?string $downloadName = null, ?string $mimeType = null): self
    {
        if (!file_exists($filePath)) {
            return self::error('File not found.', 404);
        }

        $fileName = $downloadName ?? basename($filePath);
        $fileSize = filesize($filePath);
        $mime = $mimeType ?? mime_content_type($filePath) ?: 'application/octet-stream';

        $response = new self('', 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . addslashes($fileName) . '"',
            'Content-Length' => (string) $fileSize,
            'Cache-Control' => 'no-cache, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);

        $response->filePath = $filePath;
        return $response;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}", true);
        }

        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }

        echo $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
