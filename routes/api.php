<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminAuthController;

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

// User Profile Management API - v1
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Profile information
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    
    // Profile picture management
    Route::post('/profile/picture', [ProfileController::class, 'updateProfilePicture']);
    Route::delete('/profile/picture', [ProfileController::class, 'deleteProfilePicture']);
    
    // Activity and statistics
    Route::get('/profile/activity', [ProfileController::class, 'getActivityHistory']);
    Route::get('/profile/stats', [ProfileController::class, 'getStats']);
    
    // Referral management
    Route::get('/profile/referrals', [ProfileController::class, 'getReferralInfo']);
    
    // Privacy settings
    Route::get('/profile/privacy', [ProfileController::class, 'getPrivacySettings']);
    Route::put('/profile/privacy', [ProfileController::class, 'updatePrivacySettings']);
    
    // Account management
    Route::post('/profile/deactivate', [ProfileController::class, 'deactivateAccount']);
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

// Admin Authentication Routes
Route::prefix('v1/admin/auth')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/me', [AdminAuthController::class, 'me'])->middleware('auth:sanctum');
    Route::post('/create', [AdminAuthController::class, 'createAdmin'])->middleware('auth:sanctum');
});

// Membership Management (No authentication required)
Route::prefix('v1/admin')->group(function () {
    Route::get('/memberships', [App\Http\Controllers\MembershipController::class, 'index']);
    Route::post('/memberships', [App\Http\Controllers\MembershipController::class, 'store']);
    Route::get('/memberships/{id}', [App\Http\Controllers\MembershipController::class, 'show']);
    Route::put('/memberships/{id}', [App\Http\Controllers\MembershipController::class, 'update']);
    Route::delete('/memberships/{id}', [App\Http\Controllers\MembershipController::class, 'destroy']);
});

// Admin endpoints (add admin middleware as needed)
Route::prefix('v1/admin')->middleware('auth:sanctum')->group(function () {
    // User Management
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/stats', [UserController::class, 'stats']);
    Route::get('/users/{uuid}', [UserController::class, 'show']);
    Route::put('/users/{uuid}', [UserController::class, 'update']);
    Route::post('/users/{uuid}/deactivate', [UserController::class, 'deactivate']);
    Route::post('/users/{uuid}/activate', [UserController::class, 'activate']);
    
    // Task management (specific routes first)
    Route::get('/tasks/categories', [App\Http\Controllers\Api\TaskController::class, 'getCategories']);
    Route::get('/tasks/available', [App\Http\Controllers\Api\TaskController::class, 'getAvailableTasks']);
    Route::get('/tasks/stats', [App\Http\Controllers\Api\TaskController::class, 'getTaskStats']);
    Route::post('/tasks/assign-daily', [App\Http\Controllers\Api\TaskController::class, 'assignDailyTasks']);
    Route::post('/tasks/reset-daily', [App\Http\Controllers\Api\TaskController::class, 'resetDailyTasks']);
    Route::post('/tasks/start-scheduler', [App\Http\Controllers\Api\TaskController::class, 'startScheduler']);
    
    // Task CRUD operations (parameterized routes last)
    Route::get('/tasks', [App\Http\Controllers\Api\TaskController::class, 'index']);
    Route::post('/tasks', [App\Http\Controllers\Api\TaskController::class, 'store']);
    Route::get('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'show']);
    Route::put('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'destroy']);
});
