<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Library\Adapters\Outbound\Security\JwtService;
use Library\Domain\Exceptions\InvalidCredentialsException;

final class AuthController
{
    public function __construct(
        private JwtService $jwtService
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');

        if ($username === '' || $password === '') {
            throw new InvalidCredentialsException('Credenciales inválidas.');
        }

        $cleanUsername = strtolower(trim($username));
        $user = User::where('username', $cleanUsername)->first();

        if (!$user || !Hash::check($password, $user->password_hash)) {
            throw new InvalidCredentialsException('Credenciales inválidas.');
        }

        $ttlSeconds = 28800; // 8 hours
        $token = $this->jwtService->generateToken($user->id, $user->role, $ttlSeconds);

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $ttlSeconds,
            'role' => $user->role,
        ]);
    }
}
