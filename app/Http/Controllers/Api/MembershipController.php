<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Membership;
use App\Models\UserMembership;
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
     * Purchase a membership (placeholder for payment integration)
     */
    public function purchaseMembership(Request $request): JsonResponse
    {
        $request->validate([
            'membership_id' => 'required|exists:membership,id',
            'payment_method' => 'required|string',
        ]);

        $user = $request->user();
        $membership = Membership::find($request->membership_id);

        if (!$membership->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Membership is not available',
                'error' => 'MembershipInactive'
            ], 400);
        }

        // Check if user already has an active membership
        $existingMembership = $user->activeMembership;
        if ($existingMembership && $existingMembership->membership_id == $membership->id) {
            return response()->json([
                'success' => false,
                'message' => 'You already have this membership',
                'error' => 'AlreadyHaveMembership'
            ], 400);
        }

        try {
            // Deactivate current membership if exists
            if ($existingMembership) {
                $existingMembership->update(['is_active' => false]);
            }

            // Create new membership
            $userMembership = UserMembership::create([
                'user_id' => $user->id,
                'membership_id' => $membership->id,
                'started_at' => now(),
                'expires_at' => now()->addDays($membership->duration_days ?? 30),
                'is_active' => true,
                'daily_tasks_completed' => 0,
                'last_reset_date' => today(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Membership purchased successfully',
                'data' => $userMembership->getDetails()
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase membership',
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
