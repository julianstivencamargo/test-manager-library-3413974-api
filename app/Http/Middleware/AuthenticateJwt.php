<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Library\Adapters\Outbound\Security\JwtService;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateJwt
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $authHeader = $request->header('Authorization');
        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Token de autenticación no proporcionado.',
                'details' => [],
            ], 401);
        }

        $token = substr($authHeader, 7);
        $payload = $this->jwtService->validateToken($token);

        if (!$payload || !isset($payload['sub'])) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Token de autenticación inválido o expirado.',
                'details' => [],
            ], 401);
        }

        $user = User::find($payload['sub']);
        if (!$user) {
            return response()->json([
                'code' => 'INVALID_CREDENTIALS',
                'message' => 'Usuario no encontrado.',
                'details' => [],
            ], 401);
        }

        $request->attributes->set('auth_user', $user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
