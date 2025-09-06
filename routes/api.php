<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Auth endpoints - v1 API
Route::prefix('v1')->group(function () {
    // Public auth endpoints
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/verify-email', [AuthController::class, 'verify']);
    Route::post('/auth/resend-verification', [AuthController::class, 'resendVerification']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgot']);
    Route::get('/auth/validate-reset-token', [AuthController::class, 'validateResetToken']);
    Route::post('/auth/reset-password', [AuthController::class, 'reset']);
    
    // Password reset route for Laravel's built-in password reset
    Route::get('/password/reset/{token}', function () {
        return response()->json(['message' => 'Password reset page']);
    })->name('password.reset');
    
    // Protected auth endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
    });
});
