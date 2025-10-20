<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OfflineNotificationService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OfflineNotificationController extends Controller
{
    private OfflineNotificationService $offlineNotificationService;

    public function __construct(OfflineNotificationService $offlineNotificationService)
    {
        $this->offlineNotificationService = $offlineNotificationService;
    }

    /**
     * Send notification to all users (including offline users)
     * Admin only endpoint
     */
    public function notifyAllUsers(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500',
            'url' => 'nullable|string',
            'type' => 'nullable|string'
        ]);

        $user = $request->user();
        
        // Check if user is admin
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

        $results = $this->offlineNotificationService->notifyAllUsers(
            $request->title,
            $request->body,
            $data
        );

        return response()->json([
            'success' => $results['successful'] > 0,
            'message' => "Notification sent to {$results['successful']} users, {$results['failed']} failed",
            'data' => $results
        ]);
    }

    /**
     * Send urgent notification to specific user
     */
    public function sendUrgentNotification(Request $request): JsonResponse
    {
        $request->validate([
            'user_email' => 'required|email',
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:500'
        ]);

        $user = User::where('email', $request->user_email)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $success = $this->offlineNotificationService->sendUrgentNotification(
            $user,
            $request->title,
            $request->body,
            [
                'url' => '/urgent',
                'type' => 'urgent'
            ]
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Urgent notification sent successfully' : 'Failed to send urgent notification'
        ]);
    }

    /**
     * Send maintenance notification to all users
     */
    public function notifyMaintenance(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'scheduled_time' => 'nullable|string'
        ]);

        $user = $request->user();
        
        // Check if user is admin
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $results = $this->offlineNotificationService->notifyMaintenance(
            $request->message,
            $request->scheduled_time
        );

        return response()->json([
            'success' => $results['successful'] > 0,
            'message' => "Maintenance notification sent to {$results['successful']} users",
            'data' => $results
        ]);
    }

    /**
     * Send payment notification to user
     */
    public function notifyPaymentReceived(Request $request): JsonResponse
    {
        $request->validate([
            'user_email' => 'required|email',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3'
        ]);

        $user = User::where('email', $request->user_email)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        $success = $this->offlineNotificationService->notifyPaymentReceived(
            $user,
            $request->amount,
            $request->currency ?? 'USD'
        );

        return response()->json([
            'success' => $success,
            'message' => $success ? 'Payment notification sent successfully' : 'Failed to send payment notification'
        ]);
    }
}


