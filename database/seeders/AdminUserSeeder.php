<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Aprovisiona el administrador inicial desde el entorno (T-17).
 *
 * La contraseña solo proviene de ADMIN_PASSWORD: no existe valor por defecto en el código
 * (constitución: «Todo secreto viene del entorno»).
 */
class AdminUserSeeder extends Seeder
{
    public const RESULT_CREATED = 'created';
    public const RESULT_EXISTS = 'exists';
    public const RESULT_MISSING_ENV = 'missing_env';

    public function run(): void
    {
        $this->provision();
    }

    public function provision(): string
    {
        $adminUsername = strtolower(trim((string) env('ADMIN_USERNAME', 'admin')));
        $adminPassword = (string) env('ADMIN_PASSWORD', '');

        if ($adminUsername === '' || $adminPassword === '') {
            $message = 'Administrador inicial no creado: defina ADMIN_USERNAME y ADMIN_PASSWORD en el entorno.';
            Log::warning($message);
            $this->command?->warn($message);

            return self::RESULT_MISSING_ENV;
        }

        if (User::where('username', $adminUsername)->exists()) {
            return self::RESULT_EXISTS;
        }

        User::create([
            'id' => Str::uuid()->toString(),
            'username' => $adminUsername,
            'password_hash' => Hash::make($adminPassword),
            'role' => 'admin',
        ]);

        return self::RESULT_CREATED;
    }
}
