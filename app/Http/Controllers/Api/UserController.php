<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

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
}
