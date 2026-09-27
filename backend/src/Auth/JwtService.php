<?php

declare(strict_types=1);

namespace App\Auth;

use RuntimeException;

class JwtService
{
    private string $secret;
    private int $ttl;
    private static array $blacklistedTokens = [];

    public function __construct(?string $secret = null, ?int $ttl = null)
    {
        $this->secret = $secret ?? config('app.jwt.secret', 'task-management-secret-key-change-in-production-32char-min!');
        $this->ttl = $ttl ?? (int) config('app.jwt.ttl', 86400);
    }

    public function encode(array $claims): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $now = time();
        $payload = array_merge([
            'iss' => config('app.url', 'http://127.0.0.1:8000'),
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(16)),
        ], $claims);

        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $this->secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    public function decode(string $token): array
    {
        if (self::isBlacklisted($token)) {
            throw new RuntimeException('Token has been revoked.');
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed JWT token structure.');
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $headerJson = self::base64UrlDecode($headerEncoded);
        $payloadJson = self::base64UrlDecode($payloadEncoded);
        $providedSignature = self::base64UrlDecode($signatureEncoded);

        if (!$headerJson || !$payloadJson || !$providedSignature) {
            throw new RuntimeException('Invalid token encoding.');
        }

        $header = json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256') {
            throw new RuntimeException('Unsupported token algorithm.');
        }

        $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $this->secret, true);
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Invalid token signature.');
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid token payload.');
        }

        if (isset($payload['exp']) && time() >= $payload['exp']) {
            throw new RuntimeException('Token has expired.');
        }

        return $payload;
    }

    public static function blacklist(string $token): void
    {
        self::$blacklistedTokens[$token] = time();
    }

    public static function isBlacklisted(string $token): bool
    {
        return isset(self::$blacklistedTokens[$token]);
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string|false
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
