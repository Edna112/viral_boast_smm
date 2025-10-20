<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\TaskSubmissionController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\AdminComplaintController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminPaymentController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Http\Controllers\Api\AdminWithdrawalController;
use App\Http\Controllers\Api\PaymentDetailsController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\NewAdminAuthController;
use App\Http\Controllers\Api\OfflineNotificationController;
use App\Http\Controllers\Api\WebPushController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\TaskDistributionController;
use App\Http\Controllers\Api\TaskHistoryController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\MembershipController;

// Commented out redundant user endpoint - use /api/v1/profile instead
// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

// Debug route to test API
Route::get('/test', function () {
    return response()->json(['message' => 'API is working']);
});

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
    
    // Public Web Push endpoint
    Route::get('/webpush/vapid-key', [WebPushController::class, 'getVapidKey']);
    Route::get('/webpush/debug-config', [WebPushController::class, 'debugVapidConfig']);
    
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
        
        // User account deletion
        Route::delete('/users/me', [App\Http\Controllers\Api\UserController::class, 'deleteSelf']);
        
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
    
    // Task History
    Route::get('/task-history', [TaskHistoryController::class, 'getUserHistory']);
    Route::get('/task-history/stats', [TaskHistoryController::class, 'getUserStats']);
    Route::get('/task-history/{id}', [TaskHistoryController::class, 'getHistoryEntry']);
    
    // Enhanced Task Assignment (User level)
    Route::post('/tasks/assign-enhanced', [TaskDistributionController::class, 'assignTasksToUserEnhanced']);
    Route::get('/tasks/my-status', [TaskDistributionController::class, 'getUserTaskStatus']);
    
    // Push Notifications
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::delete('/subscriptions', [SubscriptionController::class, 'destroy']);
    
    // Web Push
    Route::post('/webpush/test', [WebPushController::class, 'sendTestNotification']);
    Route::post('/webpush/send-to-all', [WebPushController::class, 'sendToAllUsers']);
    
    // Offline Notifications (for users not logged in)
    Route::post('/notifications/send-to-all', [OfflineNotificationController::class, 'notifyAllUsers']);
    Route::post('/notifications/urgent', [OfflineNotificationController::class, 'sendUrgentNotification']);
    Route::post('/notifications/maintenance', [OfflineNotificationController::class, 'notifyMaintenance']);
    Route::post('/notifications/payment', [OfflineNotificationController::class, 'notifyPaymentReceived']);
    
    // Complaints
    Route::post('/complaints', [ComplaintController::class, 'submitComplaint']);
    Route::get('/complaints', [ComplaintController::class, 'getUserComplaints']);
    Route::get('/complaints/stats', [ComplaintController::class, 'getComplaintStats']);
    Route::get('/complaints/{id}', [ComplaintController::class, 'getComplaint']);
    Route::put('/complaints/{id}', [ComplaintController::class, 'updateComplaint']);
    
    // Payments
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments/statistics', [PaymentController::class, 'statistics']);
    Route::get('/payments/{uuid}', [PaymentController::class, 'show']);
    Route::get('/payments/{uuid}/status', [PaymentController::class, 'getPaymentStatus']);
    Route::put('/payments/{uuid}', [PaymentController::class, 'update']);
    Route::delete('/payments/{uuid}', [PaymentController::class, 'destroy']);
    Route::post('/payments/{uuid}/calculate-conversion', [PaymentController::class, 'calculateConversion']);
    
    // Withdrawals
    Route::get('/withdrawals', [WithdrawalController::class, 'getUserWithdrawals']);
    Route::post('/withdrawals', [WithdrawalController::class, 'createWithdrawal']);
    Route::get('/withdrawals/{uuid}', [WithdrawalController::class, 'getWithdrawal']);
    Route::delete('/withdrawals/{uuid}', [WithdrawalController::class, 'deleteWithdrawal']);
    
    // Payment Details (User can only view)
    Route::get('/payment-details', [PaymentDetailsController::class, 'index']);
    Route::get('/payment-details/{id}', [PaymentDetailsController::class, 'show']);
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
    Route::delete('/users/{uuid}', [UserController::class, 'destroy']);
    
    // Payment Management (Admin only)
    Route::get('/payments', [AdminPaymentController::class, 'getAllPayments']);
    Route::get('/payments/{uuid}', [AdminPaymentController::class, 'getPaymentById']);
    Route::post('/payments/{uuid}/approve', [AdminPaymentController::class, 'approvePayment']);
    Route::delete('/payments/{uuid}', [AdminPaymentController::class, 'deletePayment']);
    
    // Withdrawal Management (Admin only)
    Route::get('/withdrawals', [AdminWithdrawalController::class, 'getAllWithdrawals']);
    Route::get('/withdrawals/{uuid}', [AdminWithdrawalController::class, 'getWithdrawalById']);
    Route::post('/withdrawals/{uuid}/complete', [AdminWithdrawalController::class, 'completeWithdrawal']);
    Route::post('/withdrawals/{uuid}/reject', [AdminWithdrawalController::class, 'rejectWithdrawal']);
    Route::delete('/withdrawals/{uuid}', [AdminWithdrawalController::class, 'deleteWithdrawal']);
    Route::put('/withdrawals/{uuid}', [AdminWithdrawalController::class, 'editWithdrawal']);
    
    // Payment Details Management (Admin CRUD)
    Route::get('/payment-details', [PaymentDetailsController::class, 'index']);
    Route::post('/payment-details', [PaymentDetailsController::class, 'store']);
    Route::get('/payment-details/{id}', [PaymentDetailsController::class, 'show']);
    Route::put('/payment-details/{id}', [PaymentDetailsController::class, 'update']);
    Route::delete('/payment-details/{id}', [PaymentDetailsController::class, 'destroy']);
    
    // Complaint Management (Admin only)
    Route::get('/complaints', [AdminComplaintController::class, 'getAllComplaints']);
    Route::get('/complaints/stats', [AdminComplaintController::class, 'getComplaintStats']);
    Route::get('/complaints/{id}', [AdminComplaintController::class, 'getComplaintById']);
    Route::put('/complaints/{id}/status', [AdminComplaintController::class, 'updateComplaintStatus']);
    Route::delete('/complaints/{id}', [AdminComplaintController::class, 'deleteComplaint']);
    Route::post('/complaints/bulk-update', [AdminComplaintController::class, 'bulkUpdateComplaints']);
    
    // Task History Management (Admin only)
    Route::get('/task-history', [TaskHistoryController::class, 'getAllHistory']);
    
    // Task management (specific routes first)
    Route::get('/tasks/categories', [App\Http\Controllers\Api\TaskController::class, 'getCategories']);
    Route::get('/tasks/available', [App\Http\Controllers\Api\TaskController::class, 'getAvailableTasks']);
    Route::get('/tasks/stats', [App\Http\Controllers\Api\TaskController::class, 'getTaskStats']);
    Route::post('/tasks/assign-daily', [App\Http\Controllers\Api\TaskController::class, 'assignDailyTasks']);
    Route::post('/tasks/reset-daily', [App\Http\Controllers\Api\TaskController::class, 'resetDailyTasks']);
    Route::post('/tasks/start-scheduler', [App\Http\Controllers\Api\TaskController::class, 'startScheduler']);
    
    // Enhanced Task Assignment Routes (Admin only)
    Route::post('/tasks/assign-daily-enhanced', [TaskDistributionController::class, 'assignDailyTasksEnhanced']);
    Route::post('/tasks/assign-to-user-enhanced', [TaskDistributionController::class, 'assignTasksToUserEnhanced']);
    Route::post('/tasks/assign-to-new-user', [TaskDistributionController::class, 'assignTasksToNewUser']);
    Route::get('/tasks/user-status', [TaskDistributionController::class, 'getUserTaskStatus']);
    Route::post('/tasks/reset-daily-enhanced', [TaskDistributionController::class, 'resetDailyAssignments']);
    Route::get('/tasks/distribution-stats-enhanced', [TaskDistributionController::class, 'getEnhancedDistributionStats']);
    
    // Task CRUD operations (parameterized routes last)
    Route::get('/tasks', [App\Http\Controllers\Api\TaskController::class, 'index']);
    Route::post('/tasks', [App\Http\Controllers\Api\TaskController::class, 'store']);
    Route::get('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'show']);
    Route::put('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'update']);
    Route::delete('/tasks/{id}', [App\Http\Controllers\Api\TaskController::class, 'destroy']);
});

// New Admin Management Routes (Separate Admin Table)
Route::prefix('v1/new-admin')->group(function () {
    // Admin Authentication
    Route::post('/auth/login', [NewAdminAuthController::class, 'login']);
    Route::post('/auth/logout', [NewAdminAuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('/auth/profile', [NewAdminAuthController::class, 'profile'])->middleware('auth:sanctum');
    
    // Admin CRUD Operations (Super Admin only)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/admins', [AdminController::class, 'index']);           // Get all admins
        Route::post('/admins', [AdminController::class, 'store']);          // Create admin
        Route::get('/admins/{id}', [AdminController::class, 'show']);         // Get specific admin
        Route::put('/admins/{id}', [AdminController::class, 'update']);     // Update admin
        Route::delete('/admins/{id}', [AdminController::class, 'destroy']);  // Delete admin
    });
});
