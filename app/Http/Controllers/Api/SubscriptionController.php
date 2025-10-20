<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubscriptionController extends Controller
{
    /**
     * Store a new push subscription
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
            'public_key' => 'required|string',
            'auth_token' => 'required|string',
        ]);

        $user = $request->user();
        
        // Check if subscription already exists
        $existingSubscription = UserSubscription::where('user_uuid', $user->uuid)
            ->where('endpoint', $request->endpoint)
            ->first();

        if ($existingSubscription) {
            // Update existing subscription
            $existingSubscription->update([
                'public_key' => $request->public_key,
                'auth_token' => $request->auth_token,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription updated successfully',
                'data' => $existingSubscription
            ]);
        }

        // Create new subscription
        $subscription = UserSubscription::create([
            'user_uuid' => $user->uuid,
            'endpoint' => $request->endpoint,
            'public_key' => $request->public_key,
            'auth_token' => $request->auth_token,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription created successfully',
            'data' => $subscription
        ], 201);
    }

    /**
     * Get user's subscriptions
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = UserSubscription::where('user_uuid', $user->uuid)
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subscriptions
        ]);
    }

    /**
     * Remove a subscription
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();
        $subscription = UserSubscription::where('user_uuid', $user->uuid)
            ->where('endpoint', $request->endpoint)
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription not found'
            ], 404);
        }

        $subscription->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Subscription removed successfully'
        ]);
    }
}
