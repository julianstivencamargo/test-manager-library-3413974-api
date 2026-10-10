<?php

namespace App\Providers;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // T-17: al terminar `php artisan migrate`, aprovisionar el administrador inicial desde el entorno.
        // No se migra al arrancar: la base permanece sin tablas hasta migrar (T-20).
        Event::listen(MigrationsEnded::class, function (MigrationsEnded $event): void {
            if ($event->method !== 'up') {
                return;
            }

            $this->app->make(AdminUserSeeder::class)->provision();
        });
    }
}
