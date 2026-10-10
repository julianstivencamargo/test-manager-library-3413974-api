<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Command;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'library:bootstrap-admin';
    protected $description = 'Provision initial admin user from environment variables';

    public function handle(AdminUserSeeder $seeder): int
    {
        $result = $seeder->provision();

        return match ($result) {
            AdminUserSeeder::RESULT_CREATED => $this->done('Administrador inicial creado desde el entorno.'),
            AdminUserSeeder::RESULT_EXISTS => $this->done('El administrador inicial ya existe; no se modificó.'),
            default => $this->failWith('Defina ADMIN_USERNAME y ADMIN_PASSWORD en el entorno.'),
        };
    }

    private function done(string $message): int
    {
        $this->info($message);

        return Command::SUCCESS;
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return Command::FAILURE;
    }
}
