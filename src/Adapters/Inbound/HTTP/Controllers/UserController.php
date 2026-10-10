<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\HTTP\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Library\Domain\Exceptions\UsernameTakenException;
use Library\Domain\Exceptions\ValidationException;

final class UserController
{
    public function store(Request $request): JsonResponse
    {
        $role = (string) $request->input('role', '');
        $username = (string) $request->input('username', '');
        $password = (string) $request->input('password', '');

        if ($role === 'admin') {
            throw new ValidationException('No se permite otorgar el rol admin mediante la API.');
        }

        if ($role !== 'librarian') {
            throw new ValidationException("El rol '{$role}' no es válido. Solo se permite 'librarian'.");
        }

        $cleanUsername = strtolower(trim($username));
        if ($cleanUsername === '' || strlen($password) < 6) {
            throw new ValidationException('El nombre de usuario y la contraseña son obligatorios (mínimo 6 caracteres de contraseña).');
        }

        if (User::where('username', $cleanUsername)->exists()) {
            throw new UsernameTakenException("El nombre de usuario '{$cleanUsername}' ya está registrado.");
        }

        $user = User::create([
            'id' => Str::uuid()->toString(),
            'username' => $cleanUsername,
            'password_hash' => Hash::make($password),
            'role' => 'librarian',
        ]);

        return response()->json([
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
        ], 201);
    }
}
