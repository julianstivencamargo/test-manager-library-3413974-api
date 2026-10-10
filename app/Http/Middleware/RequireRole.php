<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->attributes->get('auth_user') ?? $request->user();

        if (!$user) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'No autenticado.',
                'details' => [],
            ], 401);
        }

        if (!in_array($user->role, $roles, true)) {
            return response()->json([
                'code' => 'FORBIDDEN_ROLE',
                'message' => 'No tiene permisos para realizar esta acción.',
                'details' => [],
            ], 403);
        }

        return $next($request);
    }
}
