<?php

declare(strict_types=1);

namespace App\Http;

class Request
{
    private string $method;
    private string $path;
    private array $queryParams;
    private array $headers;
    private array $body;
    private array $files;
    private array $params = [];
    public ?array $user = null;

    public function __construct(
        string $method,
        string $path,
        array $queryParams = [],
        array $headers = [],
        array $body = [],
        array $files = []
    ) {
        $this->method = strtoupper($method);
        $this->path = '/' . trim(parse_url($path, PHP_URL_PATH) ?? '/', '/');
        $this->queryParams = $queryParams;
        $this->headers = array_change_key_case($headers, CASE_LOWER);
        $this->body = $body;
        $this->files = $files;
    }

    public static function createFromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';

        $headers = [];
        foreach ($_SERVER as $key => $val) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $val;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'AUTHORIZATION'])) {
                $headers[strtolower(str_replace('_', '-', $key))] = $val;
            }
        }

        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            foreach ($apacheHeaders as $key => $val) {
                $headers[strtolower($key)] = $val;
            }
        }

        $body = [];
        $contentType = $headers['content-type'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $input = file_get_contents('php://input');
            if ($input) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                }
            }
        } elseif ($method === 'POST') {
            $body = $_POST;
        } elseif (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            $input = file_get_contents('php://input');
            if ($input) {
                $decoded = json_decode($input, true);
                if (is_array($decoded)) {
                    $body = $decoded;
                } else {
                    parse_str($input, $body);
                }
            }
        }

        return new self($method, $uri, $_GET, $headers, $body, $_FILES);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->queryParams[$key] ?? $default;
    }

    public function allQuery(): array
    {
        return $this->queryParams;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->queryParams[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->queryParams, $this->body);
    }

    public function body(): array
    {
        return $this->body;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('authorization');
        if (!$header) {
            return null;
        }

        if (preg_match('/Bearer\s(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function allFiles(): array
    {
        return $this->files;
    }

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }
}
