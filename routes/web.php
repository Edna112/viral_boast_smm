<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebAuthController;

Route::get('/', function () {
    return view('welcome');
});

// Login route for authentication middleware
Route::get('/login', function () {
    return response()->json(['message' => 'Please login to access this resource'], 401);
})->name('login');

// Web Authentication Routes (JSON responses, session-based auth)
Route::prefix('auth')->name('auth.')->group(function () {
    // Email-based registration and verification
    Route::post('/register', [WebAuthController::class, 'register'])->name('register');
    Route::post('/verify', [WebAuthController::class, 'verify'])->name('verify');
    Route::post('/resend-verification', [WebAuthController::class, 'resendVerification'])->name('resend');
    
    // Phone-based registration and login
    Route::post('/register-phone', [WebAuthController::class, 'registerPhone'])->name('register.phone');
    Route::post('/login-phone', [WebAuthController::class, 'loginPhone'])->name('login.phone');
    
    // Traditional email login
    Route::post('/login', [WebAuthController::class, 'login'])->name('login');
    
    // Password reset
    Route::post('/forgot-password', [WebAuthController::class, 'forgotPassword'])->name('forgot');
    Route::post('/reset-password', [WebAuthController::class, 'resetPassword'])->name('reset');
    Route::get('/password/reset/{token}', function ($token) {
        return response()->json(['message' => 'Password reset page', 'token' => $token]);
    })->name('password.reset');
    
    // Logout
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
});

// Protected routes (require authentication)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [WebAuthController::class, 'dashboard'])->name('dashboard');
});

Route::get('/tasks', [App\Http\Controllers\TaskController::class, 'index']);
Route::get('/tasks/{id}', [App\Http\Controllers\TaskController::class, 'show']);
Route::post('/tasks', [App\Http\Controllers\TaskController::class, 'store']);
Route::put('/tasks/{id}', [App\Http\Controllers\TaskController::class, 'update']);
Route::delete('/tasks/{id}', [App\Http\Controllers\TaskController::class, 'destroy']);

Route::get('/memberships', [App\Http\Controllers\MembershipController::class, 'index']);
Route::get('/memberships/{id}', [App\Http\Controllers\MembershipController::class, 'show']);
Route::post('/memberships', [App\Http\Controllers\MembershipController::class, 'store']);
Route::put('/memberships/{id}', [App\Http\Controllers\MembershipController::class, 'update']);
Route::delete('/memberships/{id}', [App\Http\Controllers\MembershipController::class, 'destroy']);

Route::get('/transactions', [App\Http\Controllers\TransactionController::class, 'index']);
Route::get('/transactions/{id}', [App\Http\Controllers\TransactionController::class, 'show']);
Route::post('/transactions', [App\Http\Controllers\TransactionController::class, 'store']);
Route::put('/transactions/{id}', [App\Http\Controllers\TransactionController::class, 'update']);
Route::delete('/transactions/{id}', [App\Http\Controllers\TransactionController::class, 'destroy']);
