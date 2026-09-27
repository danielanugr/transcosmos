<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class JwtService
{
    private string $secret;
    private int $ttl;

    public function __construct()
    {
        $this->secret = config('app.key') ?: 'base64:task-management-jwt-secret-key-32chars!';
        $this->ttl = 86400;
    }

    public function generateToken(User $user): string
    {
        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
        ];

        $now = time();
        $payload = [
            'iss' => config('app.url', 'http://127.0.0.1:8000'),
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'name' => $user->name,
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $headerEncoded = self::base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));

        $signature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $this->secret, true);
        $signatureEncoded = self::base64UrlEncode($signature);

        return "{$headerEncoded}.{$payloadEncoded}.{$signatureEncoded}";
    }

    public function validateToken(string $token): array
    {
        if (Cache::has("jwt_blacklist:{$token}")) {
            throw new RuntimeException('Token has been revoked.', 401);
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed token.', 401);
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        $headerJson = self::base64UrlDecode($headerEncoded);
        $payloadJson = self::base64UrlDecode($payloadEncoded);
        $providedSignature = self::base64UrlDecode($signatureEncoded);

        if (!$headerJson || !$payloadJson || !$providedSignature) {
            throw new RuntimeException('Invalid token encoding.', 401);
        }

        $expectedSignature = hash_hmac('sha256', "{$headerEncoded}.{$payloadEncoded}", $this->secret, true);
        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new RuntimeException('Invalid signature.', 401);
        }

        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Invalid payload.', 401);
        }

        if (isset($payload['exp']) && time() >= $payload['exp']) {
            throw new RuntimeException('Token expired.', 401);
        }

        return $payload;
    }

    public function revokeToken(string $token): void
    {
        try {
            $payload = $this->validateToken($token);
            $secondsUntilExpiry = max(60, ($payload['exp'] ?? time()) - time());
            Cache::put("jwt_blacklist:{$token}", true, $secondsUntilExpiry);
        } catch (\Throwable $e) {
            // Already invalid
        }
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
