<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\Request;
use App\Http\Response;

class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array|string $roles = ['admin'])
    {
        $this->allowedRoles = is_array($roles) ? $roles : explode(',', $roles);
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$request->user) {
            return Response::error('Unauthenticated.', 401);
        }

        $userRole = $request->user['role'] ?? 'member';
        if (!in_array($userRole, $this->allowedRoles, true)) {
            return Response::error('Forbidden. Insufficient permissions for this action.', 403);
        }

        return $next($request);
    }
}
