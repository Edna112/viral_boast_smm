<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\TaskSubmissionController;
use App\Http\Controllers\Api\ComplaintController;

// Commented out redundant user endpoint - use /api/v1/profile instead
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

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
    
    // Anonymous complaint submission (no authentication required)
    Route::post('/complaints/anonymous', [ComplaintController::class, 'submitAnonymousComplaint']);
    
    // Referral validation (public endpoints)
    Route::post('/referrals/validate', [App\Http\Controllers\Api\ReferralController::class, 'validateReferralCode']);
    Route::get('/referrals/user/{referral_code}', [App\Http\Controllers\Api\ReferralController::class, 'getUserByReferralCode']);
    
    // Protected auth endpoints
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        // Commented out redundant auth/me endpoint - use /api/v1/profile instead
        // Route::get('/auth/me', [AuthController::class, 'me']);
        
        // Referral management (authenticated endpoints)
        Route::get('/referrals/stats', [App\Http\Controllers\Api\ReferralController::class, 'getReferralStats']);
        Route::post('/referrals/can-use', [App\Http\Controllers\Api\ReferralController::class, 'canUseReferralCode']);
        
        // Account management (authenticated endpoints)
        Route::get('/account', [App\Http\Controllers\Api\AccountController::class, 'getAccount']);
        Route::get('/account/balance', [App\Http\Controllers\Api\AccountController::class, 'getBalance']);
        Route::get('/account/financial-stats', [App\Http\Controllers\Api\AccountController::class, 'getFinancialStats']);
        Route::patch('/account', [App\Http\Controllers\Api\AccountController::class, 'updateAccount']);
        Route::post('/account/add-funds', [App\Http\Controllers\Api\AccountController::class, 'addFunds']);
        Route::post('/account/deduct-funds', [App\Http\Controllers\Api\AccountController::class, 'deductFunds']);
        Route::post('/account/transfer', [App\Http\Controllers\Api\AccountController::class, 'transferFunds']);
        Route::get('/account/transactions', [App\Http\Controllers\Api\AccountController::class, 'getTransactionHistory']);
        Route::post('/account/deactivate', [App\Http\Controllers\Api\AccountController::class, 'deactivateAccount']);
        Route::post('/account/activate', [App\Http\Controllers\Api\AccountController::class, 'activateAccount']);
        
        // Task distribution algorithm (single route)
        Route::get('/task-distribution/run', [App\Http\Controllers\Api\TaskDistributionController::class, 'distributeTasks']);
    });
});

// User Profile Management API - v1
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Profile information
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    
    // Profile picture management (legacy routes - use PUT /profile instead)
    // Route::post('/profile/picture', [ProfileController::class, 'updateProfilePicture']);
    // Route::delete('/profile/picture', [ProfileController::class, 'deleteProfilePicture']);
    
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
    
    // Task Submissions
    Route::post('/task-submissions', [TaskSubmissionController::class, 'submitProof']);
    Route::get('/task-submissions', [TaskSubmissionController::class, 'getUserSubmissions']);
    Route::get('/task-submissions/stats', [TaskSubmissionController::class, 'getSubmissionStats']);
    Route::get('/task-submissions/{id}', [TaskSubmissionController::class, 'getSubmission']);
    Route::put('/task-submissions/{id}', [TaskSubmissionController::class, 'updateSubmission']);
    Route::delete('/task-submissions/{id}', [TaskSubmissionController::class, 'deleteSubmission']);
    
    // Complaints
    Route::post('/complaints', [ComplaintController::class, 'submitComplaint']);
    Route::get('/complaints', [ComplaintController::class, 'getUserComplaints']);
    Route::get('/complaints/stats', [ComplaintController::class, 'getComplaintStats']);
    Route::get('/complaints/{id}', [ComplaintController::class, 'getComplaint']);
    Route::put('/complaints/{id}', [ComplaintController::class, 'updateComplaint']);
});

// Task Management API - v1
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Task endpoints
    Route::get('/tasks/my-tasks', [App\Http\Controllers\Api\TaskController::class, 'getUserTasks']);
    Route::post('/tasks/{assignmentId}/complete', [App\Http\Controllers\Api\TaskController::class, 'completeTask']);
    Route::get('/tasks/completion-history', [App\Http\Controllers\Api\TaskController::class, 'getCompletionHistory']);
    Route::get('/tasks/stats', [App\Http\Controllers\Api\TaskController::class, 'getUserStats']);
    Route::get('/tasks/{assignmentId}/details', [App\Http\Controllers\Api\TaskController::class, 'getTaskDetails']);
    Route::get('/tasks/{taskId}/user-details', [App\Http\Controllers\Api\TaskController::class, 'getUserTaskDetails']);
    Route::put('/tasks/{taskId}/update', [App\Http\Controllers\Api\TaskController::class, 'updateUserTask']);
    
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
