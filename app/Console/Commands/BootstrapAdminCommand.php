<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Console\Command;

class BootstrapAdminCommand extends Command
{
    protected $signature = 'library:bootstrap-admin';
    protected $description = 'Provision initial admin user from environment variables';

    public function handle(): int
    {
        $this->call(AdminUserSeeder::class);
        $this->info('Admin user verified/provisioned.');

        return Command::SUCCESS;
    }
}
