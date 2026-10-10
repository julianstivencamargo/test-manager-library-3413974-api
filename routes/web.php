<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/health', \Library\Adapters\Inbound\HTTP\Controllers\HealthCheckController::class);

