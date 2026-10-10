<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminUsername = strtolower(trim((string) env('ADMIN_USERNAME', 'admin')));
        $adminPassword = (string) env('ADMIN_PASSWORD', 'Admin1234*');

        if ($adminUsername !== '' && User::where('username', $adminUsername)->doesntExist()) {
            User::create([
                'id' => Str::uuid()->toString(),
                'username' => $adminUsername,
                'password_hash' => Hash::make($adminPassword),
                'role' => 'admin',
            ]);
        }
    }
}
