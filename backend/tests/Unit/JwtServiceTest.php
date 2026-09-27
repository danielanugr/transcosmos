<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class JwtServiceTest extends TestCase
{
    use RefreshDatabase;

    private JwtService $jwt;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwt = new JwtService();

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'jwt_test@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
        ]);
    }

    public function test_can_generate_valid_jwt_token(): void
    {
        $token = $this->jwt->generateToken($this->user);

        $this->assertNotEmpty($token);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);

        $payload = $this->jwt->validateToken($token);
        $this->assertIsArray($payload);
        $this->assertSame($this->user->id, $payload['sub']);
        $this->assertSame('jwt_test@example.com', $payload['email']);
        $this->assertSame('admin', $payload['role']);
    }

    public function test_tampered_token_throws_invalid_signature_exception(): void
    {
        $token = $this->jwt->generateToken($this->user);
        $parts = explode('.', $token);

        // Tamper with payload (middle part)
        $tamperedPayload = JwtService::base64UrlEncode(json_encode(['sub' => 9999, 'role' => 'superadmin']));
        $tamperedToken = $parts[0] . '.' . $tamperedPayload . '.' . $parts[2];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid signature.');

        $this->jwt->validateToken($tamperedToken);
    }

    public function test_revoked_token_is_rejected(): void
    {
        $token = $this->jwt->generateToken($this->user);

        $payloadBefore = $this->jwt->validateToken($token);
        $this->assertIsArray($payloadBefore);

        $this->jwt->revokeToken($token);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Token has been revoked.');

        $this->jwt->validateToken($token);
    }

    public function test_malformed_token_throws_exception(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed token.');

        $this->jwt->validateToken('only_two.parts');
    }
}
