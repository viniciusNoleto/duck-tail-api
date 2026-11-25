<?php

use App\Base\Traits\Response;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\User\RegistrationRequestController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

// REST só para a sessão e a administração de usuários (Postgres). O domínio (grafo) é o GraphQL em
// `/graphql` (Lighthouse, `config/lighthouse.php`, schema em `graphql/schema.graphql`).

// Rotas públicas com rate limit (limites em `AppServiceProvider::configureRateLimiting`; estourou → 429).
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:register')->name('auth.register');
    Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth')->name('auth.me');
    Route::put('password', [AuthController::class, 'changePassword'])->middleware(['auth', 'throttle:password'])->name('auth.password');
    Route::prefix('token')->group(function () {
        Route::post('refresh', [AuthController::class, 'refreshToken'])->middleware('throttle:refresh')->name('auth.token.refresh');
    });
});

Route::middleware('auth')->group(function () {
    // Administração: só admins (403 para usuário comum).
    Route::middleware('admin')->group(function () {
        Route::prefix('users')->group(function () {
            Route::get('', [UserController::class, 'index'])->name('users.index');
            Route::delete('{user_id}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::put('{user_id}/password', [UserController::class, 'resetPassword'])->name('users.password');
        });

        Route::prefix('registration-requests')->group(function () {
            Route::get('', [RegistrationRequestController::class, 'index'])->name('registration-requests.index');
            Route::prefix('{registration_request_id}')->group(function () {
                Route::post('approve', [RegistrationRequestController::class, 'approve'])->name('registration-requests.approve');
                Route::delete('', [RegistrationRequestController::class, 'destroy'])->name('registration-requests.destroy');
            });
        });
    });
});

// Fora do `auth`: rota inexistente é 404 com ou sem token (dentro do grupo, sem token virava 401).
Route::fallback(fn() => Response::errorResponse('messages.not_found', 404));
