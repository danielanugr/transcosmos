<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AuthenticateWithJwt
{
    public function __construct(private JwtService $jwtService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json([
                'success' => false,
                'error' => 'Authentication required. Missing Bearer token.',
            ], 401);
        }

        try {
            $payload = $this->jwtService->validateToken($token);
            $user = User::find($payload['sub']);
            if ($user) {
                auth()->setUser($user);
                $request->setUserResolver(fn () => $user);
                return $next($request);
            }
        } catch (Throwable $e) {
            // Check if Sanctum token exists
            try {
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken && $accessToken->tokenable) {
                    $user = $accessToken->tokenable;
                    auth()->setUser($user);
                    $request->setUserResolver(fn () => $user);
                    return $next($request);
                }
            } catch (Throwable) {
                // Table might not exist or token invalid
            }

            return response()->json([
                'success' => false,
                'error' => 'Unauthorized: ' . $e->getMessage(),
            ], 401);
        }

        return response()->json([
            'success' => false,
            'error' => 'Unauthorized. User associated with token not found.',
        ], 401);
    }
}
