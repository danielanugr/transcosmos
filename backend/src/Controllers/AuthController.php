<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\AuthService;
use App\Utils\Validator;
use Throwable;

class AuthController
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    public function login(Request $request): Response
    {
        $validation = Validator::validate($request->body(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        if (!$validation['valid']) {
            return Response::error('Validation failed.', 422, $validation['errors']);
        }

        try {
            $data = $validation['data'];
            $authData = $this->authService->login($data['email'], $data['password']);
            return Response::success($authData, 'Authentication successful.');
        } catch (Throwable $e) {
            $code = ($e->getCode() >= 400 && $e->getCode() < 500) ? (int) $e->getCode() : 401;
            return Response::error($e->getMessage(), $code);
        }
    }

    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();
        if ($token) {
            $this->authService->logout($token);
        }

        return Response::success(null, 'Successfully logged out.');
    }

    public function me(Request $request): Response
    {
        if (!$request->user) {
            return Response::error('Unauthenticated.', 401);
        }

        return Response::success($request->user);
    }
}
