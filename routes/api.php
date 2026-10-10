<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Library\Adapters\Inbound\HTTP\Controllers\HealthCheckController;

Route::get('/health', HealthCheckController::class);
