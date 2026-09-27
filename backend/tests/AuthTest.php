<?php

declare(strict_types=1);

namespace Tests;

use App\Auth\JwtService;
use App\Services\AuthService;
use App\Repositories\UserRepository;
use RuntimeException;

class AuthTest
{
    private AuthService $authService;
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->userRepository = new UserRepository();
    }

    public function run(): void
    {
        echo "Running Auth Tests...\n";
        $this->testSuccessfulLogin();
        $this->testFailedLoginInvalidPassword();
        $this->testFailedLoginNonexistentEmail();
        $this->testJwtPayloadAndDecoding();
        $this->testTokenBlacklistOnLogout();
        echo " -> Auth Tests Passed (5/5)\n\n";
    }

    private function testSuccessfulLogin(): void
    {
        $result = $this->authService->login('alice@example.com', 'password123');

        assert(!empty($result['token']), 'Login should return a JWT token string.');
        assert($result['token_type'] === 'Bearer', 'Token type should be Bearer.');
        assert($result['user']['email'] === 'alice@example.com', 'User email in payload should match.');
        assert($result['user']['role'] === 'admin', 'User role should be admin.');
        assert(!isset($result['user']['password']), 'Password hash must never be returned in response.');
    }

    private function testFailedLoginInvalidPassword(): void
    {
        try {
            $this->authService->login('alice@example.com', 'wrongpassword');
            assert(false, 'Login should have thrown an exception for invalid password.');
        } catch (RuntimeException $e) {
            assert($e->getCode() === 401, 'Invalid password should throw with 401 code.');
        }
    }

    private function testFailedLoginNonexistentEmail(): void
    {
        try {
            $this->authService->login('nonexistent@example.com', 'password123');
            assert(false, 'Login should have thrown an exception for nonexistent email.');
        } catch (RuntimeException $e) {
            assert($e->getCode() === 401, 'Nonexistent email should throw with 401 code.');
        }
    }

    private function testJwtPayloadAndDecoding(): void
    {
        $jwt = new JwtService();
        $token = $jwt->encode([
            'sub' => 99,
            'email' => 'test@example.com',
            'role' => 'manager',
        ]);

        $payload = $jwt->decode($token);
        assert($payload['sub'] === 99, 'Decoded subject ID should match.');
        assert($payload['email'] === 'test@example.com', 'Decoded email should match.');
        assert($payload['role'] === 'manager', 'Decoded role should match.');
        assert(isset($payload['exp']) && $payload['exp'] > time(), 'Token exp should be in future.');
    }

    private function testTokenBlacklistOnLogout(): void
    {
        $jwt = new JwtService();
        $token = $jwt->encode(['sub' => 1, 'email' => 'logout@example.com', 'role' => 'member']);

        // First decode succeeds
        $payload = $jwt->decode($token);
        assert($payload['sub'] === 1, 'Token should decode before logout.');

        // Blacklist token
        $this->authService->logout($token);

        try {
            $jwt->decode($token);
            assert(false, 'Decoded blacklisted token should throw exception.');
        } catch (RuntimeException $e) {
            assert(str_contains($e->getMessage(), 'revoked'), 'Exception message should confirm token revocation.');
        }
    }
}
