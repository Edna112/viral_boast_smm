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
    
    // Development-only endpoint for testing verification codes
    Route::get('/auth/verification-code', [AuthController::class, 'getVerificationCode']);
    
    // Protected auth endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
    });
});

// Task Management API - v1
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Task endpoints
    Route::get('/tasks/my-tasks', [App\Http\Controllers\Api\TaskController::class, 'getUserTasks']);
    Route::post('/tasks/{assignmentId}/complete', [App\Http\Controllers\Api\TaskController::class, 'completeTask']);
    Route::get('/tasks/completion-history', [App\Http\Controllers\Api\TaskController::class, 'getCompletionHistory']);
    Route::get('/tasks/stats', [App\Http\Controllers\Api\TaskController::class, 'getUserStats']);
    Route::get('/tasks/{assignmentId}/details', [App\Http\Controllers\Api\TaskController::class, 'getTaskDetails']);
    
    // Membership endpoints
    Route::get('/memberships', [App\Http\Controllers\Api\MembershipController::class, 'index']);
    Route::get('/memberships/my-membership', [App\Http\Controllers\Api\MembershipController::class, 'getUserMembership']);
    Route::get('/memberships/history', [App\Http\Controllers\Api\MembershipController::class, 'getUserMembershipHistory']);
    Route::post('/memberships/purchase', [App\Http\Controllers\Api\MembershipController::class, 'purchaseMembership']);
    Route::get('/memberships/{id}', [App\Http\Controllers\Api\MembershipController::class, 'show']);
    Route::get('/memberships/vip/comparison', [App\Http\Controllers\Api\MembershipController::class, 'getVipComparison']);
});

// Admin endpoints (add admin middleware as needed)
Route::prefix('v1/admin')->middleware('auth:sanctum')->group(function () {
    Route::get('/tasks/available', [App\Http\Controllers\Api\TaskController::class, 'getAvailableTasks']);
    Route::get('/tasks/stats', [App\Http\Controllers\Api\TaskController::class, 'getTaskStats']);
    Route::post('/tasks/assign-daily', [App\Http\Controllers\Api\TaskController::class, 'assignDailyTasks']);
    Route::post('/tasks/reset-daily', [App\Http\Controllers\Api\TaskController::class, 'resetDailyTasks']);
});
