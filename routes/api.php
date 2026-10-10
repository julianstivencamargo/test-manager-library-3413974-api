<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Library\Adapters\Inbound\HTTP\Controllers\AuthController;
use Library\Adapters\Inbound\HTTP\Controllers\BookController;
use Library\Adapters\Inbound\HTTP\Controllers\CategoryController;
use Library\Adapters\Inbound\HTTP\Controllers\HealthCheckController;
use Library\Adapters\Inbound\HTTP\Controllers\LoanController;
use Library\Adapters\Inbound\HTTP\Controllers\MemberController;
use Library\Adapters\Inbound\HTTP\Controllers\ReportController;
use Library\Adapters\Inbound\HTTP\Controllers\UserController;

// Public endpoints
Route::get('/health', HealthCheckController::class);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/books/{id}/cover', [BookController::class, 'getCover']);

// Authenticated endpoints
Route::middleware(['jwt.auth'])->group(function () {
    // Categories
    Route::get('/categories', [CategoryController::class, 'index']);

    // Books
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/books/{id}', [BookController::class, 'show']);

    // Admin-only Book routes
    Route::middleware(['role:admin'])->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::put('/books/{id}/cover', [BookController::class, 'uploadCover']);
        Route::delete('/books/{id}/cover', [BookController::class, 'deleteCover']);
        Route::get('/reports/most-borrowed', [ReportController::class, 'mostBorrowed']);
    });

    // Admin and Librarian routes
    Route::middleware(['role:admin,librarian'])->group(function () {
        // Members
        Route::get('/members', [MemberController::class, 'index']);
        Route::get('/members/{id}', [MemberController::class, 'show']);
        Route::post('/members', [MemberController::class, 'store']);

        // Loans
        Route::post('/loans', [LoanController::class, 'store']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/loans/overdue', [LoanController::class, 'overdue']);
        Route::get('/loans/{id}', [LoanController::class, 'show']);
        Route::post('/loans/{loan_id}/items/{item_id}/return', [LoanController::class, 'returnItem']);
    });
});
