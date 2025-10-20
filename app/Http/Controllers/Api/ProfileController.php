<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserMembership;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class ProfileController extends Controller
{

    /**
     * Get current user's profile information
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();
            
            // Load the membership relationship (same as login route)
            $user->load('membership');
            
            // Get membership with fallback if relationship fails
            $membership = $user->membership;
            if (!$membership && $user->membership_level) {
                $membership = \App\Models\Membership::find($user->membership_level);
            }
            
            // Debug: Log membership information
            \Log::info('Profile Debug', [
                'user_id' => $user->id,
                'membership_level' => $user->membership_level,
                'membership_loaded' => $membership ? $membership->membership_name : 'NULL',
                'membership_id' => $membership ? $membership->id : 'NULL'
            ]);

            // Get referral statistics
            $referralStats = $this->getReferralStats($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'profile_image' => $user->profile_image,
                        'email_verified_at' => $user->email_verified_at,
                        'phone_verified_at' => $user->phone_verified_at,
                        'referral_code' => $user->referral_code,
                        'referred_by' => $user->referred_by,
                        'total_points' => $user->total_points,
                        'total_tasks' => $user->total_tasks,
                        'tasks_completed_today' => $user->tasks_completed_today,
                        'last_task_reset_date' => $user->last_task_reset_date,
                        'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                        'last_submission_reset_date' => $user->last_submission_reset_date,
                        'account_balance' => $user->account_balance,
                        'membership_level' => $user->membership_level,
                        'role' => $user->role,
                        'isActive' => $user->isActive,
                        'is_active' => $user->is_active,
                        'is_admin' => $user->is_admin,
                        'deactivated_at' => $user->deactivated_at,
                        'deactivation_reason' => $user->deactivation_reason,
                        'lastLogin' => $user->lastLogin,
                        'profile_visibility' => $user->profile_visibility,
                        'show_email' => $user->show_email,
                        'show_phone' => $user->show_phone,
                        'show_activity' => $user->show_activity,
                        'email_notifications' => $user->email_notifications,
                        'sms_notifications' => $user->sms_notifications,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        // Membership relationship
                        'membership' => $membership ? [
                            'id' => $membership->id,
                            'membership_name' => $membership->membership_name,
                            'membership_icon' => $membership->membership_icon,
                            'description' => $membership->description,
                            'tasks_per_day' => $membership->tasks_per_day,
                            'max_tasks' => $membership->max_tasks,
                            'price' => $membership->price,
                            'benefit_amount_per_task' => $membership->benefit_amount_per_task,
                            'is_active' => $membership->is_active,
                        ] : null,
                        // Computed fields for convenience
                        'emailVerified' => !is_null($user->email_verified_at),
                        'phoneVerified' => !is_null($user->phone_verified_at),
                        'isActive' => $user->isActive,
                    ],
                    'referral_stats' => $referralStats,
                    'assigned_tasks' => $user->assigned_tasks ?? [],
                    'completed_tasks' => $user->completed_tasks ?? [],
                    'inprogress_tasks' => $user->inprogress_tasks ?? [],
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('ProfileController getProfile error: ' . $e->getMessage(), [
                'user_id' => $request->user() ? $request->user()->id : null,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile information',
                'error' => 'ProfileRetrievalError'
            ], 500);
        }
    }

    /**
     * Update user's basic profile information including profile image
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->id)],
            'profile_image' => ['sometimes', 'string', 'url'], // Accept image URL from frontend
            'membership_id' => ['sometimes', 'integer', 'exists:memberships,id'],
        ]);

        // Check if email is being changed (only if email is provided)
        $emailChanged = isset($data['email']) && $user->email !== $data['email'];
        $phoneChanged = isset($data['phone']) && $user->phone !== $data['phone'];

        // Prepare update data - only include fields that are provided
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }
        
        if (isset($data['phone'])) {
            $updateData['phone'] = $data['phone'];
        }
        
        if (isset($data['profile_image'])) {
            $updateData['profile_image'] = $data['profile_image'];
        }
        
        if (isset($data['membership_id'])) {
            $updateData['membership_id'] = $data['membership_id'];
        }

        $user->update($updateData);

        // If email changed, mark as unverified and send verification
        if ($emailChanged) {
            $user->update([
                'email_verified_at' => null,
                'email_verification_code' => null,
                'email_verification_expires_at' => null,
            ]);
            
            // Send new verification code
            $this->sendEmailVerification($user);
        }

        // If phone changed, mark as unverified
        if ($phoneChanged) {
            $user->update([
                'phone_verified_at' => null,
                'phone_verification_code' => null,
                'phone_verification_expires_at' => null,
            ]);
        }

        // Prepare response data
        $responseData = [
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_image' => $user->profile_image ? $this->getProfileImageUrl($user->profile_image) : null,
                'email_verified_at' => $user->email_verified_at,
                'phone_verified_at' => $user->phone_verified_at,
                'referral_code' => $user->referral_code,
                'total_points' => $user->total_points,
                'total_tasks' => $user->total_tasks,
                'tasks_completed_today' => $user->tasks_completed_today,
                'account_balance' => $user->account_balance,
                'membership_level' => $user->membership_level,
                'role' => $user->role,
                'isActive' => $user->isActive,
                'is_active' => $user->is_active,
                'is_admin' => $user->is_admin,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'email_verification_required' => $emailChanged,
            'phone_verification_required' => $phoneChanged,
            'updated_fields' => array_keys($updateData), // Show which fields were updated
            'assigned_tasks' => $user->assigned_tasks ?? [],
            'completed_tasks' => $user->completed_tasks ?? [],
            'inprogress_tasks' => $user->inprogress_tasks ?? [],
        ];

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $responseData
        ]);
    }

    /**
     * Update user's password
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Verify current password
        if (!Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
                'error' => 'InvalidCurrentPassword'
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * Update user's profile picture URL (legacy method - use updateProfile instead)
     */
    public function updateProfilePicture(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'profile_image' => ['sometimes', 'string', 'url'], // Accept image URL from frontend
        ]);

        $user->update([
            'profile_image' => $request->profile_image,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Profile image updated successfully',
            'data' => [
                'profile_image_url' => $request->profile_image,
            ]
        ]);
    }

    /**
     * Delete user's profile image URL (legacy method - use updateProfile instead)
     */
    public function deleteProfilePicture(Request $request)
    {
        $user = $request->user();

            $user->update(['profile_image' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Profile image deleted successfully'
        ]);
    }

    /**
     * Get user's activity history
     */
    public function getActivityHistory(Request $request)
    {
        $user = $request->user();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        // Get task completion history
        $taskHistory = $user->taskAssignments()
            ->with('task')
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);

        // Get membership history
        $membershipHistory = UserMembership::where('user_uuid', $user->uuid)
            ->with('membership')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get referral history
        $referralHistory = Referral::where('referrer_uuid', $user->uuid)
            ->with('referredUser')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'task_history' => [
                    'data' => $taskHistory->items(),
                    'pagination' => [
                        'current_page' => $taskHistory->currentPage(),
                        'last_page' => $taskHistory->lastPage(),
                        'per_page' => $taskHistory->perPage(),
                        'total' => $taskHistory->total(),
                    ]
                ],
                'membership_history' => $membershipHistory,
                'referral_history' => $referralHistory,
            ]
        ]);
    }

    /**
     * Get user's statistics and achievements
     */
    public function getStats(Request $request)
    {
        $user = $request->user();

        // Calculate various statistics
        $totalTasksCompleted = $user->taskAssignments()->where('status', 'completed')->count();
        $totalPointsEarned = $user->total_points ?? 0;
        $currentStreak = $this->calculateCurrentStreak($user);
        $longestStreak = $this->calculateLongestStreak($user);
        $referralStats = $this->getReferralStats($user);

        // Get recent activity (last 7 days)
        $recentActivity = $user->taskAssignments()
            ->where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->subDays(7))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_tasks_completed' => $totalTasksCompleted,
                'total_points_earned' => $totalPointsEarned,
                'current_streak' => $currentStreak,
                'longest_streak' => $longestStreak,
                'recent_activity' => $recentActivity,
                'referral_stats' => $referralStats,
                'membership_level' => $this->getMembershipLevel($user),
                'rank' => $this->calculateUserRank($user),
            ]
        ]);
    }

    /**
     * Get user's referral information
     */
    public function getReferralInfo(Request $request)
    {
        $user = $request->user();
        $referralStats = $this->getReferralStats($user);

        // Get recent referrals
        $recentReferrals = Referral::where('referrer_uuid', $user->uuid)
            ->with('referredUser')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'referral_code' => $user->referral_code,
                'referral_url' => url('/register?ref=' . $user->referral_code),
                'stats' => $referralStats,
                'recent_referrals' => $recentReferrals,
            ]
        ]);
    }

    /**
     * Deactivate user account
     */
    public function deactivateAccount(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password is incorrect',
                'error' => 'InvalidPassword'
            ], 422);
        }

        // Deactivate account
        $user->update([
            'is_active' => false,
            'deactivated_at' => Carbon::now(),
            'deactivation_reason' => $request->reason,
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deactivated successfully'
        ]);
    }

    /**
     * Get user's privacy settings
     */
    public function getPrivacySettings(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'profile_visibility' => $user->profile_visibility ?? 'public',
                'show_email' => $user->show_email ?? false,
                'show_phone' => $user->show_phone ?? false,
                'show_activity' => $user->show_activity ?? true,
                'email_notifications' => $user->email_notifications ?? true,
                'sms_notifications' => $user->sms_notifications ?? false,
            ]
        ]);
    }

    /**
     * Update user's privacy settings
     */
    public function updatePrivacySettings(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'profile_visibility' => ['sometimes', 'string', 'in:public,private,friends'],
            'show_email' => ['sometimes', 'boolean'],
            'show_phone' => ['sometimes', 'boolean'],
            'show_activity' => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
            'sms_notifications' => ['sometimes', 'boolean'],
        ]);

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Privacy settings updated successfully',
            'data' => $data
        ]);
    }

    /**
     * Helper method to get referral statistics
     */
    private function getReferralStats($user)
    {
        try {
            $totalReferrals = Referral::where('referrer_uuid', $user->uuid)->count();
            $activeReferrals = Referral::where('referrer_uuid', $user->uuid)
                ->where('status', 'active')
                ->count();
            $pendingReferrals = Referral::where('referrer_uuid', $user->uuid)
                ->where('status', 'pending')
                ->count();

            return [
                'total_referrals' => $totalReferrals,
                'active_referrals' => $activeReferrals,
                'pending_referrals' => $pendingReferrals,
            ];
        } catch (\Exception $e) {
            \Log::error('ProfileController getReferralStats error: ' . $e->getMessage(), [
                'user_id' => $user->id ?? null,
                'user_uuid' => $user->uuid ?? null
            ]);
            
            return [
                'total_referrals' => 0,
                'active_referrals' => 0,
                'pending_referrals' => 0,
            ];
        }
    }

    /**
     * Helper method to calculate current streak
     */
    private function calculateCurrentStreak($user)
    {
        // Implementation depends on your streak calculation logic
        // This is a placeholder
        return 0;
    }

    /**
     * Helper method to calculate longest streak
     */
    private function calculateLongestStreak($user)
    {
        // Implementation depends on your streak calculation logic
        // This is a placeholder
        return 0;
    }

    /**
     * Helper method to get membership level
     */
    private function getMembershipLevel($user)
    {
        $membership = UserMembership::where('user_uuid', $user->uuid)
            ->where('is_active', true)
            ->with('membership')
            ->first();

        return $membership ? $membership->membership->name : 'Basic';
    }

    /**
     * Helper method to calculate user rank
     */
    private function calculateUserRank($user)
    {
        // Implementation depends on your ranking system
        // This is a placeholder
        return 'Beginner';
    }

    /**
     * Get profile image URL (handles both Cloudinary and local storage)
     */
    private function getProfileImageUrl($profileImage)
    {
        // If it's already a full URL (Cloudinary), return as is
        if (filter_var($profileImage, FILTER_VALIDATE_URL)) {
            return $profileImage;
        }

        // If it's a local storage path, return Storage URL
        return Storage::url($profileImage);
    }


    /**
     * Helper method to send email verification
     */
    private function sendEmailVerification($user)
    {
        try {
            $verificationCode = random_int(100000, 999999);
            
            $user->update([
                'email_verification_code' => (string) $verificationCode,
                'email_verification_expires_at' => Carbon::now()->addMinutes(2),
            ]);

            // Send verification email
            $emailContent = "
                <h2>Email Verification Required</h2>
                <p>Hello {$user->name},</p>
                <p>You've updated your email address. Please verify your new email with this code:</p>
                <p><strong style='font-size: 24px; color: #007bff;'>{$verificationCode}</strong></p>
                <p>This code will expire in 2 minutes.</p>
                <br>
                <p>Best regards,<br>PIS SMM Team</p>
            ";
            
            Mail::html($emailContent, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Verify Your New Email - PIS SMM');
            });
        } catch (\Exception $e) {
            // Log the error but don't fail the profile update
            \Log::error('Failed to send email verification: ' . $e->getMessage());
        }
    }

    /**
     * Get user's assigned tasks (all task assignments)
     */
    private function getUserAssignedTasks($user)
    {
        try {
            $assignments = $user->taskAssignments()
                ->with('task')
                ->orderBy('assigned_at', 'desc')
                ->get();

            return $assignments->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'task_id' => $assignment->task_id,
                    'assigned_at' => $assignment->assigned_at,
                    'expires_at' => $assignment->expires_at,
                    'status' => $assignment->status,
                    'base_points' => $assignment->base_points,
                    'vip_multiplier' => $assignment->vip_multiplier,
                    'final_reward' => $assignment->final_reward,
                    'completed_at' => $assignment->completed_at,
                    'completion_photo_url' => $assignment->completion_photo_url,
                    'task' => [
                        'id' => $assignment->task->id,
                        'title' => $assignment->task->title,
                        'description' => $assignment->task->description,
                        'category' => $assignment->task->category,
                        'task_type' => $assignment->task->task_type,
                        'platform' => $assignment->task->platform,
                        'instructions' => $assignment->task->instructions,
                        'target_url' => $assignment->task->target_url,
                        'benefit' => $assignment->task->benefit,
                        'is_active' => $assignment->task->is_active,
                        'task_status' => $assignment->task->task_status,
                        'priority' => $assignment->task->priority,
                        'threshold_value' => $assignment->task->threshold_value,
                        'task_completion_count' => $assignment->task->task_completion_count,
                        'task_distribution_count' => $assignment->task->task_distribution_count,
                    ]
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Failed to get user assigned tasks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's completed tasks
     */
    private function getUserCompletedTasks($user)
    {
        try {
            $assignments = $user->taskAssignments()
                ->with('task')
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->get();

            return $assignments->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'task_id' => $assignment->task_id,
                    'assigned_at' => $assignment->assigned_at,
                    'expires_at' => $assignment->expires_at,
                    'status' => $assignment->status,
                    'base_points' => $assignment->base_points,
                    'vip_multiplier' => $assignment->vip_multiplier,
                    'final_reward' => $assignment->final_reward,
                    'completed_at' => $assignment->completed_at,
                    'completion_photo_url' => $assignment->completion_photo_url,
                    'task' => [
                        'id' => $assignment->task->id,
                        'title' => $assignment->task->title,
                        'description' => $assignment->task->description,
                        'category' => $assignment->task->category,
                        'task_type' => $assignment->task->task_type,
                        'platform' => $assignment->task->platform,
                        'instructions' => $assignment->task->instructions,
                        'target_url' => $assignment->task->target_url,
                        'benefit' => $assignment->task->benefit,
                        'is_active' => $assignment->task->is_active,
                        'task_status' => $assignment->task->task_status,
                        'priority' => $assignment->task->priority,
                        'threshold_value' => $assignment->task->threshold_value,
                        'task_completion_count' => $assignment->task->task_completion_count,
                        'task_distribution_count' => $assignment->task->task_distribution_count,
                    ]
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Failed to get user completed tasks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get user's in-progress tasks (pending assignments)
     */
    private function getUserInProgressTasks($user)
    {
        try {
            $assignments = $user->taskAssignments()
                ->with('task')
                ->where('status', 'pending')
                ->orderBy('assigned_at', 'desc')
                ->get();

            return $assignments->map(function ($assignment) {
                return [
                    'assignment_id' => $assignment->id,
                    'task_id' => $assignment->task_id,
                    'assigned_at' => $assignment->assigned_at,
                    'expires_at' => $assignment->expires_at,
                    'status' => $assignment->status,
                    'base_points' => $assignment->base_points,
                    'vip_multiplier' => $assignment->vip_multiplier,
                    'final_reward' => $assignment->final_reward,
                    'completed_at' => $assignment->completed_at,
                    'completion_photo_url' => $assignment->completion_photo_url,
                    'time_remaining' => $assignment->expires_at ? $assignment->expires_at->diffForHumans() : null,
                    'is_expired' => $assignment->expires_at ? $assignment->expires_at->isPast() : false,
                    'task' => [
                        'id' => $assignment->task->id,
                        'title' => $assignment->task->title,
                        'description' => $assignment->task->description,
                        'category' => $assignment->task->category,
                        'task_type' => $assignment->task->task_type,
                        'platform' => $assignment->task->platform,
                        'instructions' => $assignment->task->instructions,
                        'target_url' => $assignment->task->target_url,
                        'benefit' => $assignment->task->benefit,
                        'is_active' => $assignment->task->is_active,
                        'task_status' => $assignment->task->task_status,
                        'priority' => $assignment->task->priority,
                        'threshold_value' => $assignment->task->threshold_value,
                        'task_completion_count' => $assignment->task->task_completion_count,
                        'task_distribution_count' => $assignment->task->task_distribution_count,
                    ]
                ];
            })->toArray();
        } catch (\Exception $e) {
            \Log::error('Failed to get user in-progress tasks: ' . $e->getMessage());
            return [];
        }
    }

}
