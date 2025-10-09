<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\UserMembership;
use App\Models\User;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MembershipController extends Controller
{
    /**
     * Get all available memberships
     */
    public function index(): JsonResponse
    {
        $memberships = Membership::where('is_active', true)
                               ->orderBy('id')
                               ->get()
                               ->map(function ($membership) {
                                   return [
                                       'id' => $membership->id,
                                       'membership_name' => $membership->membership_name,
                                       'description' => $membership->description,
                                       'tasks_per_day' => $membership->tasks_per_day,
                                       'max_tasks' => $membership->max_tasks,
                                       'price' => $membership->price,
                                       'benefit_amount_per_task' => $membership->benefit_amount_per_task,
                                       'is_active' => $membership->is_active,
                                       'created_at' => $membership->created_at,
                                       'updated_at' => $membership->updated_at,
                                   ];
                               });

        return response()->json([
            'success' => true,
            'data' => [
                'memberships' => $memberships,
                'total_memberships' => $memberships->count(),
            ]
        ]);
    }

    /**
     * Get user's current membership
     */
    public function getUserMembership(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeMembership = $user->activeMembership;

        if (!$activeMembership) {
            return response()->json([
                'success' => false,
                'message' => 'No active membership found',
                'error' => 'NoActiveMembership'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $activeMembership->getDetails()
        ]);
    }

    /**
     * Get user's membership history
     */
    public function getUserMembershipHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $memberships = $user->memberships()
                           ->with('membership')
                           ->orderBy('pivot_created_at', 'desc')
                           ->get()
                               ->map(function ($membership) {
                                   return [
                                       'id' => $membership->pivot->id,
                                       'membership' => [
                                           'id' => $membership->id,
                                           'membership_name' => $membership->membership_name,
                                           'description' => $membership->description,
                                           'tasks_per_day' => $membership->tasks_per_day,
                                           'max_tasks' => $membership->max_tasks,
                                           'task_link' => $membership->task_link,
                                           'benefits' => $membership->benefits,
                                           'price' => $membership->price,
                                           'duration_days' => $membership->duration_days,
                                           'reward_multiplier' => $membership->reward_multiplier,
                                           'priority_level' => $membership->priority_level,
                                           'is_active' => $membership->is_active,
                                           'created_at' => $membership->created_at,
                                           'updated_at' => $membership->updated_at,
                                       ],
                                       'subscription' => [
                                           'started_at' => $membership->pivot->started_at,
                                           'expires_at' => $membership->pivot->expires_at,
                                           'is_active' => $membership->pivot->is_active,
                                           'remaining_days' => $membership->pivot->expires_at->diffInDays(now()),
                                       ],
                                       'purchased_at' => $membership->pivot->created_at,
                                   ];
                               });

        return response()->json([
            'success' => true,
            'data' => [
                'memberships' => $memberships,
                'total_memberships' => $memberships->count(),
            ]
        ]);
    }

    /**
     * Purchase a membership with balance validation and deduction
     */
    public function purchaseMembership(Request $request): JsonResponse
    {
        $request->validate([
            'user_uuid' => 'required|string|exists:users,uuid',
            'membership_id' => 'required|exists:membership,id',
            'membership_name' => 'required|string',
        ]);

        $userUuid = $request->user_uuid;
        $membershipId = $request->membership_id;
        $membershipName = $request->membership_name;

        // Find the user
        $user = User::where('uuid', $userUuid)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
                'error' => 'UserNotFound'
            ], 404);
        }

        // Find the membership
        $membership = Membership::find($membershipId);
        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Membership not found',
                'error' => 'MembershipNotFound'
            ], 404);
        }

        // Verify membership name matches
        if ($membership->membership_name !== $membershipName) {
            return response()->json([
                'success' => false,
                'message' => 'Membership name does not match',
                'error' => 'MembershipNameMismatch'
            ], 400);
        }

        if (!$membership->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Membership is not available',
                'error' => 'MembershipInactive'
            ], 400);
        }

        // Get or create user's account
        $account = Account::getOrCreateForUser($userUuid);

        // Check if user has sufficient balance (only if membership has a price > 0)
        if ($membership->price > 0 && !$account->hasSufficientBalance($membership->price)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
                'error' => 'InsufficientBalance',
                'data' => [
                    'required_amount' => $membership->price,
                    'current_balance' => $account->balance,
                    'shortfall' => $membership->price - $account->balance
                ]
            ], 400);
        }

        // Check if user already has an active membership
        $existingMembership = $user->activeMembership()->first();
        if ($existingMembership && $existingMembership->id == $membership->id) {
            return response()->json([
                'success' => false,
                'message' => 'You already have this membership',
                'error' => 'AlreadyHaveMembership'
            ], 400);
        }

        try {
            // Start database transaction
            \DB::beginTransaction();

            // Deduct funds from user's account (only if membership has a price > 0)
            if ($membership->price > 0) {
                if (!$account->deductFunds($membership->price, 'membership_purchase')) {
                    throw new \Exception('Failed to deduct funds from account');
                }
            }

            // Deactivate current membership if exists
            if ($existingMembership) {
                $existingMembership->pivot->update(['is_active' => false]);
            }

            // Update user's membership level
            $user->update(['membership_level' => $membership->id]);

            // Create new membership record in user_memberships table
            $userMembership = UserMembership::create([
                'user_uuid' => $user->uuid,
                'membership_id' => $membership->id,
                'started_at' => now(),
                'expires_at' => now()->addDays($membership->duration_days ?? 30),
                'is_active' => true,
                'daily_tasks_completed' => 0,
                'last_reset_date' => today(),
            ]);

            // Commit transaction
            \DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Membership purchased successfully',
                'data' => [
                    'membership' => [
                        'id' => $membership->id,
                        'membership_name' => $membership->membership_name,
                        'description' => $membership->description,
                        'price' => $membership->price,
                        'tasks_per_day' => $membership->tasks_per_day,
                        'duration_days' => $membership->duration_days ?? 30,
                    ],
                    'user_membership' => [
                        'id' => $userMembership->id,
                        'started_at' => $userMembership->started_at,
                        'expires_at' => $userMembership->expires_at,
                        'is_active' => $userMembership->is_active,
                    ],
                    'account' => [
                        'balance_before' => $membership->price > 0 ? $account->balance + $membership->price : $account->balance,
                        'balance_after' => $account->balance,
                        'amount_deducted' => $membership->price,
                        'is_free_membership' => $membership->price == 0,
                    ]
                ]
            ], 201);

        } catch (\Exception $e) {
            // Rollback transaction
            \DB::rollBack();
            
            \Log::error('Membership purchase failed: ' . $e->getMessage(), [
                'user_uuid' => $userUuid,
                'membership_id' => $membershipId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase membership: ' . $e->getMessage(),
                'error' => 'PurchaseFailed'
            ], 500);
        }
    }

    /**
     * Get membership details
     */
    public function show(int $id): JsonResponse
    {
        $membership = Membership::where('is_active', true)->find($id);

        if (!$membership) {
            return response()->json([
                'success' => false,
                'message' => 'Membership not found',
                'error' => 'MembershipNotFound'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $membership->id,
                'membership_name' => $membership->membership_name,
                'description' => $membership->description,
                'tasks_per_day' => $membership->tasks_per_day,
                'max_tasks' => $membership->max_tasks,
                'task_link' => $membership->task_link,
                'benefits' => $membership->benefits,
                'price' => $membership->price,
                'duration_days' => $membership->duration_days,
                'reward_multiplier' => $membership->reward_multiplier,
                'priority_level' => $membership->priority_level,
                'is_active' => $membership->is_active,
                'created_at' => $membership->created_at,
                'updated_at' => $membership->updated_at,
            ]
        ]);
    }

    /**
     * Get VIP level comparison
     */
    public function getVipComparison(): JsonResponse
    {
        $memberships = Membership::where('is_active', true)
                               ->orderBy('priority_level')
                               ->get()
                               ->map(function ($membership) {
                                   return [
                                       'id' => $membership->id,
                                       'membership_name' => $membership->membership_name,
                                       'description' => $membership->description,
                                       'tasks_per_day' => $membership->tasks_per_day,
                                       'max_tasks' => $membership->max_tasks,
                                       'task_link' => $membership->task_link,
                                       'benefits' => $membership->benefits,
                                       'price' => $membership->price,
                                       'duration_days' => $membership->duration_days,
                                       'reward_multiplier' => $membership->reward_multiplier,
                                       'priority_level' => $membership->priority_level,
                                       'is_active' => $membership->is_active,
                                       'example_reward' => [
                                           'base_task_points' => 10,
                                           'vip_reward' => 10 * $membership->reward_multiplier,
                                       ],
                                       'created_at' => $membership->created_at,
                                       'updated_at' => $membership->updated_at,
                                   ];
                               });

        return response()->json([
            'success' => true,
            'data' => [
                'vip_levels' => $memberships,
                'comparison_note' => 'All users get the same tasks, but VIP members earn more points per task',
            ]
        ]);
    }
}
