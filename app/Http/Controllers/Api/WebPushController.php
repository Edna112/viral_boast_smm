<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WebPushService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebPushController extends Controller
{
    private WebPushService $webPushService;

    public function __construct(WebPushService $webPushService)
    {
        $this->webPushService = $webPushService;
    }

    /**
     * Get VAPID public key for frontend
     */
    public function getVapidKey(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'public_key' => $this->webPushService->getVapidPublicKey()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'ServerError',
                'details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            ], 500);
        }
    }

    /**
     * Debug VAPID configuration (for troubleshooting)
     */
    public function debugVapidConfig(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->webPushService->getVapidStatus()
        ]);
    }

    /**
     * Send test notification to authenticated user
     */
    public function sendTestNotification(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $success = $this->webPushService->sendToUser(
            $user,
            'Test Notification',
            'This is a test push notification from PIS!',
            [
                'url' => '/dashboard',
                'type' => 'test'
            ]
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Test notification sent successfully' : 'Failed to send test notification'
        ]);
    }

    /**
     * Send notification to all users (Admin only)
     */
    public function sendToAllUsers(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'url' => 'nullable|string',
            'type' => 'nullable|string'
        ]);

        $user = $request->user();
        
        // Check if user is admin (you can adjust this based on your admin system)
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $data = [
            'url' => $request->url ?? '/',
            'type' => $request->type ?? 'announcement'
        ];

        // Get all active subscriptions
        $subscriptions = \App\Models\UserSubscription::where('is_active', true)->get();
        
        if ($subscriptions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscriptions found'
            ]);
        }

        $results = $this->webPushService->sendToMultipleSubscriptions(
            $subscriptions->toArray(),
            $request->title,
            $request->body,
            $data
        );

        return response()->json([
            'success' => $results['success_count'] > 0,
            'message' => "Notification sent to {$results['success_count']} users, {$results['failure_count']} failed",
            'data' => $results
        ]);
    }
}
