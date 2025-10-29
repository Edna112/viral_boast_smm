<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Services\EnhancedTaskAssignmentService;
use App\Models\Membership;
use App\Models\UserMembership;

class UserController extends Controller
{
    /**
     * Display a listing of all users (Admin only)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get query parameters
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $status = $request->get('status'); // active, inactive, all
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');

            // Validate pagination
            $perPage = min(max($perPage, 1), 100); // Limit between 1-100

            // Build query
            $query = User::query();

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone', 'like', "%{$search}%")
                      ->orWhere('referral_code', 'like', "%{$search}%");
                });
            }

            // Apply status filter
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }

            // Apply sorting
            $allowedSortFields = ['name', 'email', 'created_at', 'email_verified_at', 'total_points'];
            if (in_array($sortBy, $allowedSortFields)) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Get paginated results
            $users = $query->paginate($perPage);

            // Transform the data to hide sensitive information
            $users->getCollection()->transform(function ($user) {
                return [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'referral_code' => $user->referral_code,
                    'referred_by' => $user->referred_by,
                    'total_points' => $user->total_points,
                    'tasks_completed_today' => $user->tasks_completed_today,
                    'is_active' => $user->is_active,
                    'email_verified_at' => $user->email_verified_at,
                    'phone_verified_at' => $user->phone_verified_at,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'profile_visibility' => $user->profile_visibility,
                    'show_email' => $user->show_email,
                    'show_phone' => $user->show_phone,
                    'show_activity' => $user->show_activity,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => [
                    'users' => $users->items(),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem(),
                    ],
                    'filters' => [
                        'search' => $search,
                        'status' => $status,
                        'sort_by' => $sortBy,
                        'sort_order' => $sortOrder,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified user (Admin only)
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $user = User::where('uuid', $uuid)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => 'UserNotFound'
                ], 404);
            }

            // Get user with relationships
            $user->load(['referrer', 'memberships']);

            return response()->json([
                'success' => true,
                'message' => 'User retrieved successfully',
                'data' => [
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'referral_code' => $user->referral_code,
                        'referred_by' => $user->referred_by,
                        'referrer' => $user->referrer ? [
                            'uuid' => $user->referrer->uuid,
                            'name' => $user->referrer->name,
                            'referral_code' => $user->referrer->referral_code,
                        ] : null,
                        'total_points' => $user->total_points,
                        'tasks_completed_today' => $user->tasks_completed_today,
                        'last_task_reset_date' => $user->last_task_reset_date,
                        'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                        'last_submission_reset_date' => $user->last_submission_reset_date,
                        'is_active' => $user->is_active,
                        'deactivated_at' => $user->deactivated_at,
                        'deactivation_reason' => $user->deactivation_reason,
                        'email_verified_at' => $user->email_verified_at,
                        'phone_verified_at' => $user->phone_verified_at,
                        'profile_picture' => $user->profile_picture,
                        'profile_visibility' => $user->profile_visibility,
                        'show_email' => $user->show_email,
                        'show_phone' => $user->show_phone,
                        'show_activity' => $user->show_activity,
                        'email_notifications' => $user->email_notifications,
                        'sms_notifications' => $user->sms_notifications,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'active_memberships' => $user->memberships->where('pivot.is_active', true)->count(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new user (Admin only)
     * This allows admins to create users directly without requiring them to register
     * Follows the same process as registration but skips verification
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate the request - same as registration but admin can set additional fields
            $data = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:users,email|max:255',
                'phone' => 'nullable|string|max:32|unique:users,phone',
                'password' => 'required|string|min:8',
                'referralCode' => 'nullable|string|max:10',
                'profile_image' => 'nullable|string|max:500',
                'total_points' => 'nullable|integer|min:0',
                'account_balance' => 'nullable|numeric|min:0',
                'is_active' => 'nullable|boolean',
                'is_admin' => 'nullable|boolean',
                'profile_visibility' => 'nullable|string|in:public,private',
            ]);

            // Ensure either email or phone is provided (same as registration)
            if (empty($data['email']) && empty($data['phone'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Either email or phone number is required.',
                    'error' => 'MissingContactInfo',
                    'details' => ['field' => 'email_or_phone']
                ], 400);
            }

            // 1. Validate Referral Code (if provided) - same as registration
            $referrer = null;
            if (!empty($data['referralCode'])) {
                $validation = User::validateReferralCodeWithLimits($data['referralCode']);
                if (!$validation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $validation['message'],
                        'error' => $validation['error'],
                        'details' => ['field' => 'referralCode']
                    ], 400);
                }
                $referrer = User::getByReferralCode($data['referralCode']);
            }

            // 2. Hash Password - same as registration
            $hashedPassword = Hash::make($data['password']);

            // 3. Generate Referral Code for New User - same as registration
            $newReferralCode = $this->generateUniqueReferralCode();

            // 4. Create User Record - same as registration but with email verified
            $userData = [
                'name' => $data['name'],
                'password' => $hashedPassword,
                'referral_code' => $newReferralCode,
                'referred_by' => $referrer ? $referrer->uuid : null,
                'email_verified_at' => now(), // Admin creates verified user
                'is_active' => $data['is_active'] ?? true,
                'is_admin' => $data['is_admin'] ?? false,
                'profile_visibility' => $data['profile_visibility'] ?? 'public',
                'total_points' => $data['total_points'] ?? 0,
                'account_balance' => $data['account_balance'] ?? 0,
            ];

            // Add email or phone based on what was provided - same as registration
            if (!empty($data['email'])) {
                $userData['email'] = $data['email'];
            }
            if (!empty($data['phone'])) {
                $userData['phone'] = $data['phone'];
            }

            // Create the user
            $user = User::create($userData);

            // 5. Assign basic membership by default - same as registration
            $membershipResult = $this->assignBasicMembershipToUser($user);

            // 6. Create account for the new user - same as registration
            $this->createUserAccount($user);

            // 7. Handle Referral Bonuses (if valid referral code was provided) - same as registration
            if ($referrer) {
                // Update referrer's account balance and total_bonus by $5
                $this->updateReferrerAccount($referrer);
                
                // Process direct referral bonus for the referrer
                $referrer->processDirectReferralBonus($user);
                
                // Process indirect referral bonus for the referrer's referrer (Level 1)
                if ($referrer->referred_by) {
                    $indirectReferrer = User::where('uuid', $referrer->referred_by)->first();
                    if ($indirectReferrer) {
                        $indirectReferrer->processIndirectReferralBonus($user);
                    }
                }
            }

            // 8. Automatically assign tasks to the new user
            $taskAssignmentResult = null;
            try {
                $taskService = new EnhancedTaskAssignmentService();
                $taskAssignmentResult = $taskService->assignTasksToNewUser($user);
            } catch (\Exception $e) {
                // Log the error but don't fail user creation
                \Log::error('Failed to assign tasks to new user', [
                    'user_uuid' => $user->uuid,
                    'error' => $e->getMessage()
                ]);
            }

            // 9. Load membership relationship for response - same as registration
            $user->load('membership');

            // 10. Response - same format as registration
            $responseData = [
                'userId' => $user->uuid,
                'referralCode' => $newReferralCode,
                'membership' => $user->membership ? [
                    'id' => $user->membership->id,
                    'membership_name' => $user->membership->membership_name,
                    'description' => $user->membership->description,
                    'tasks_per_day' => $user->membership->tasks_per_day,
                    'max_tasks' => $user->membership->max_tasks,
                    'price' => $user->membership->price,
                    'benefit_amount_per_task' => $user->membership->benefit_amount_per_task,
                    'is_active' => $user->membership->is_active,
                ] : null,
                'email_verified' => true,
                'admin_created' => true,
                'basic_membership_assigned' => $membershipResult['success'],
                'membership_info' => $membershipResult,
                'tasks_assigned' => $taskAssignmentResult ? $taskAssignmentResult['assigned_tasks'] : 0,
                'task_assignment_success' => $taskAssignmentResult ? $taskAssignmentResult['success'] : false,
                'task_assignment_errors' => $taskAssignmentResult ? $taskAssignmentResult['errors'] : []
            ];

            if (!empty($data['email'])) {
                $responseData['email'] = $user->email;
            }
            if (!empty($data['phone'])) {
                $responseData['phone'] = $user->phone;
            }

            return response()->json([
                'success' => true,
                'message' => 'User created successfully by admin. User can login immediately.',
                'data' => $responseData
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin can login as any user by email
     */
    public function loginAsUser(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $data = $request->validate([
                'email' => 'required|email',
            ]);

            // Find the user by email
            $user = User::where('email', $data['email'])->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => 'UserNotFound'
                ], 404);
            }

            // Check if user is active
            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User account is deactivated',
                    'error' => 'UserDeactivated'
                ], 403);
            }

            // Revoke all existing tokens for this user
            $user->tokens()->delete();

            // Create a new token for the admin to use as this user
            $token = $user->createToken('admin-login-as-' . $user->uuid)->plainTextToken;

            // Load user relationships
            $user->load(['membership', 'memberships', 'account', 'referrals']);

            // Return user data with token
            return response()->json([
                'success' => true,
                'message' => 'Successfully logged in as user',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'admin_login' => true
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to login as user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified user (Admin only)
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = User::where('uuid', $uuid)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => 'UserNotFound'
                ], 404);
            }

            // Validate the request
            $data = $request->validate([
                'name' => ['sometimes', 'string', 'max:255'],
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->uuid, 'uuid')],
                'phone' => ['sometimes', 'nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($user->uuid, 'uuid')],
                'is_active' => ['sometimes', 'boolean'],
                'deactivation_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
                'total_points' => ['sometimes', 'integer', 'min:0'],
                'profile_visibility' => ['sometimes', 'string', 'in:public,private,friends'],
                'show_email' => ['sometimes', 'boolean'],
                'show_phone' => ['sometimes', 'boolean'],
                'show_activity' => ['sometimes', 'boolean'],
                'email_notifications' => ['sometimes', 'boolean'],
                'sms_notifications' => ['sometimes', 'boolean'],
            ]);

            // Handle deactivation
            if (isset($data['is_active']) && !$data['is_active'] && $user->is_active) {
                $data['deactivated_at'] = now();
            } elseif (isset($data['is_active']) && $data['is_active'] && !$user->is_active) {
                $data['deactivated_at'] = null;
                $data['deactivation_reason'] = null;
            }

            // Update user
            $user->update($data);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->phone,
                        'is_active' => $user->is_active,
                        'total_points' => $user->total_points,
                        'tasks_completed_today' => $user->tasks_completed_today,
                        'last_task_reset_date' => $user->last_task_reset_date,
                        'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                        'last_submission_reset_date' => $user->last_submission_reset_date,
                        'updated_at' => $user->updated_at,
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate a user (Admin only)
     */
    public function deactivate(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = User::where('uuid', $uuid)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => 'UserNotFound'
                ], 404);
            }

            if (!$user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already deactivated',
                    'error' => 'UserAlreadyDeactivated'
                ], 400);
            }

            $data = $request->validate([
                'reason' => ['required', 'string', 'max:500']
            ]);

            $user->update([
                'is_active' => false,
                'deactivated_at' => now(),
                'deactivation_reason' => $data['reason']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deactivated successfully',
                'data' => [
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'is_active' => $user->is_active,
                        'deactivated_at' => $user->deactivated_at,
                        'deactivation_reason' => $user->deactivation_reason,
                        'tasks_completed_today' => $user->tasks_completed_today,
                        'last_task_reset_date' => $user->last_task_reset_date,
                        'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                        'last_submission_reset_date' => $user->last_submission_reset_date,
                    ]
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate a user (Admin only)
     */
    public function activate(string $uuid): JsonResponse
    {
        try {
            $user = User::where('uuid', $uuid)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => 'UserNotFound'
                ], 404);
            }

            if ($user->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is already active',
                    'error' => 'UserAlreadyActive'
                ], 400);
            }

            $user->update([
                'is_active' => true,
                'deactivated_at' => null,
                'deactivation_reason' => null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully',
                'data' => [
                    'user' => [
                        'uuid' => $user->uuid,
                        'name' => $user->name,
                        'is_active' => $user->is_active,
                        'deactivated_at' => $user->deactivated_at,
                        'tasks_completed_today' => $user->tasks_completed_today,
                        'last_task_reset_date' => $user->last_task_reset_date,
                        'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                        'last_submission_reset_date' => $user->last_submission_reset_date,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete a user account (Admin only)
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = User::where('uuid', $uuid)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                    'error' => 'UserNotFound'
                ], 404);
            }

            // Validate confirmation
            $data = $request->validate([
                'confirmation' => ['required', 'string', 'in:DELETE'],
                'reason' => ['required', 'string', 'max:500']
            ]);

            // Start database transaction
            \DB::beginTransaction();

            try {
                // Store user data for logging before deletion
                $userData = [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'total_points' => $user->total_points,
                    'account_balance' => $user->account_balance ?? 0,
                    'created_at' => $user->created_at,
                    'deletion_reason' => $data['reason'],
                    'deleted_by' => $request->user()->uuid ?? 'system',
                    'deleted_at' => now()
                ];

                // Delete related data first (to avoid foreign key constraints)
                
                // Delete user's personal access tokens
                $user->tokens()->delete();
                
                // Delete user's account if exists
                if ($user->account) {
                    $user->account->delete();
                }
                
                // Delete user's payments
                $user->payments()->delete();
                
                // Delete user's withdrawals
                $user->withdrawals()->delete();
                
                // Delete user's task assignments
                $user->taskAssignments()->delete();
                
                // Delete user's referrals (both as referrer and referred)
                $user->referrals()->delete();
                $user->referredBy()->delete();
                
                // Delete user's memberships
                $user->memberships()->detach();
                
                // Delete user's task submissions
                if (method_exists($user, 'taskSubmissions')) {
                    $user->taskSubmissions()->delete();
                }
                
                // Delete user's complaints
                if (method_exists($user, 'complaints')) {
                    $user->complaints()->delete();
                }

                // Finally delete the user
                $user->delete();

                // Log the deletion
                \Log::info('User permanently deleted', $userData);

                // Commit transaction
                \DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'User account permanently deleted',
                    'data' => [
                        'deleted_user' => [
                            'uuid' => $userData['uuid'],
                            'name' => $userData['name'],
                            'email' => $userData['email'],
                            'total_points' => $userData['total_points'],
                            'account_balance' => $userData['account_balance'],
                            'created_at' => $userData['created_at'],
                            'deletion_reason' => $userData['deletion_reason'],
                            'deleted_at' => $userData['deleted_at']
                        ]
                    ]
                ]);

            } catch (\Exception $e) {
                // Rollback transaction
                \DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to delete user: ' . $e->getMessage(), [
                'user_uuid' => $uuid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete user account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete current user's own account
     */
    public function deleteSelf(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                    'error' => 'Unauthenticated'
                ], 401);
            }

            // Validate confirmation
            $data = $request->validate([
                'confirmation' => ['required', 'string', 'in:DELETE'],
                'reason' => ['required', 'string', 'max:500'],
                'password' => ['required', 'string'] // Require password confirmation
            ]);

            // Verify user's password
            if (!\Hash::check($data['password'], $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid password',
                    'error' => 'InvalidPassword'
                ], 400);
            }

            // Start database transaction
            \DB::beginTransaction();

            try {
                // Store user data for logging before deletion
                $userData = [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'total_points' => $user->total_points,
                    'account_balance' => $user->account_balance ?? 0,
                    'created_at' => $user->created_at,
                    'deletion_reason' => $data['reason'],
                    'deleted_by' => 'self',
                    'deleted_at' => now()
                ];

                // Delete related data first (to avoid foreign key constraints)
                
                // Delete user's personal access tokens
                $user->tokens()->delete();
                
                // Delete user's account if exists
                if ($user->account) {
                    $user->account->delete();
                }
                
                // Delete user's payments
                $user->payments()->delete();
                
                // Delete user's withdrawals
                $user->withdrawals()->delete();
                
                // Delete user's task assignments
                $user->taskAssignments()->delete();
                
                // Delete user's referrals (both as referrer and referred)
                $user->referrals()->delete();
                $user->referredBy()->delete();
                
                // Delete user's memberships
                $user->memberships()->detach();
                
                // Delete user's task submissions
                if (method_exists($user, 'taskSubmissions')) {
                    $user->taskSubmissions()->delete();
                }
                
                // Delete user's complaints
                if (method_exists($user, 'complaints')) {
                    $user->complaints()->delete();
                }

                // Finally delete the user
                $user->delete();

                // Log the deletion
                \Log::info('User self-deleted account', $userData);

                // Commit transaction
                \DB::commit();

                return response()->json([
                    'success' => true,
                    'message' => 'Your account has been permanently deleted',
                    'data' => [
                        'deleted_user' => [
                            'uuid' => $userData['uuid'],
                            'name' => $userData['name'],
                            'email' => $userData['email'],
                            'total_points' => $userData['total_points'],
                            'account_balance' => $userData['account_balance'],
                            'created_at' => $userData['created_at'],
                            'deletion_reason' => $userData['deletion_reason'],
                            'deleted_at' => $userData['deleted_at']
                        ]
                    ]
                ]);

            } catch (\Exception $e) {
                // Rollback transaction
                \DB::rollBack();
                throw $e;
            }

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Failed to self-delete user: ' . $e->getMessage(), [
                'user_uuid' => $request->user()->uuid ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to permanently delete your account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user statistics (Admin only)
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('is_active', true)->count(),
                'inactive_users' => User::where('is_active', false)->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'unverified_users' => User::whereNull('email_verified_at')->count(),
                'users_with_phone' => User::whereNotNull('phone')->count(),
                'users_with_referrals' => User::whereNotNull('referred_by')->count(),
                'users_with_points' => User::where('total_points', '>', 0)->count(),
                'recent_registrations' => User::where('created_at', '>=', now()->subDays(7))->count(),
                'monthly_registrations' => User::where('created_at', '>=', now()->subMonth())->count(),
            ];

            return response()->json([
                'success' => true,
                'message' => 'User statistics retrieved successfully',
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign basic membership to a new user
     */
    private function assignBasicMembershipToUser(User $user): array
    {
        $result = [
            'success' => false,
            'membership_id' => null,
            'membership_name' => null,
            'errors' => []
        ];

        try {
            // Find the basic membership
            $basicMembership = Membership::where('membership_name', 'Basic')
                ->where('is_active', true)
                ->first();

            if (!$basicMembership) {
                $result['errors'][] = 'Basic membership not found';
                \Log::warning("Basic membership not found when assigning to user: {$user->uuid}");
                return $result;
            }

            // Update user's membership level
            $user->update(['membership_level' => $basicMembership->id]);

            // Create UserMembership record
            $userMembership = UserMembership::create([
                'user_uuid' => $user->uuid,
                'membership_id' => $basicMembership->id,
                'started_at' => now(),
                'expires_at' => null, // Basic membership doesn't expire
                'is_active' => true,
                'daily_tasks_completed' => 0,
                'last_reset_date' => today(),
            ]);

            $result['success'] = true;
            $result['membership_id'] = $basicMembership->id;
            $result['membership_name'] = $basicMembership->membership_name;
            $result['user_membership_id'] = $userMembership->id;

            \Log::info("Basic membership assigned to user: {$user->uuid} (Membership ID: {$basicMembership->id})");

        } catch (\Exception $e) {
            $result['errors'][] = $e->getMessage();
            \Log::error("Failed to assign basic membership to user {$user->uuid}: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Generate a unique referral code for new user
     */
    private function generateUniqueReferralCode(): string
    {
        do {
            $code = strtoupper(substr(md5(uniqid()), 0, 8));
        } while (User::where('referral_code', $code)->exists());
        
        return $code;
    }

    /**
     * Create account for new user with zero values
     */
    private function createUserAccount(User $user): void
    {
        try {
            \App\Models\Account::createForUser($user->uuid);
            \Log::info("Account created for user: {$user->uuid}");
        } catch (\Exception $e) {
            \Log::error("Failed to create account for user {$user->uuid}: " . $e->getMessage());
        }
    }

    /**
     * Update referrer's account with $5 bonus
     */
    private function updateReferrerAccount(User $referrer): void
    {
        try {
            $account = \App\Models\Account::getOrCreateForUser($referrer->uuid);
            $account->addFunds(5.00, 'referral');
            \Log::info("Referral bonus of $5 added to referrer account: {$referrer->uuid}");
        } catch (\Exception $e) {
            \Log::error("Failed to update referrer account {$referrer->uuid}: " . $e->getMessage());
        }
    }
}
