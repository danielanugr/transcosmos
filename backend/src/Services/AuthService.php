<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\JwtService;
use App\Repositories\UserRepository;
use RuntimeException;

class AuthService
{
    private UserRepository $userRepository;
    private JwtService $jwtService;

    public function __construct(?UserRepository $userRepository = null, ?JwtService $jwtService = null)
    {
        $this->userRepository = $userRepository ?? new UserRepository();
        $this->jwtService = $jwtService ?? new JwtService();
    }

    public function login(string $email, string $password): array
    {
        $user = $this->userRepository->findByEmailWithPassword($email);
        if (!$user) {
            throw new RuntimeException('Invalid credentials provided.', 401);
        }

        if (!password_verify($password, $user['password'])) {
            throw new RuntimeException('Invalid credentials provided.', 401);
        }

        $token = $this->jwtService->encode([
            'sub' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'name' => $user['name'],
        ]);

        unset($user['password']);

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => config('app.jwt.ttl', 86400),
            'user' => $user,
        ];
    }

    public function logout(string $token): void
    {
        JwtService::blacklist($token);
    }

    public function me(int $userId): ?array
    {
        return $this->userRepository->findById($userId);
    }
}
