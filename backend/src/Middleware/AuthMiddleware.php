<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\JwtService;
use App\Http\Request;
use App\Http\Response;
use App\Repositories\UserRepository;
use Throwable;

class AuthMiddleware
{
    private JwtService $jwtService;
    private UserRepository $userRepository;

    public function __construct(?JwtService $jwtService = null, ?UserRepository $userRepository = null)
    {
        $this->jwtService = $jwtService ?? new JwtService();
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return Response::error('Authentication required. Missing Bearer token.', 401);
        }

        try {
            $payload = $this->jwtService->decode($token);
            $userId = (int) ($payload['sub'] ?? 0);

            $user = $this->userRepository->findById($userId);
            if (!$user) {
                return Response::error('User associated with this token no longer exists.', 401);
            }

            unset($user['password']);
            $request->user = $user;

            return $next($request);
        } catch (Throwable $e) {
            return Response::error('Unauthorized: ' . $e->getMessage(), 401);
        }
    }
}
