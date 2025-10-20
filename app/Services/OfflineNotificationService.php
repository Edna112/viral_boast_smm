<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Example service showing how to send notifications to offline users
 */
class OfflineNotificationService
{
    private WebPushService $webPushService;

    public function __construct(WebPushService $webPushService)
    {
        $this->webPushService = $webPushService;
    }

    /**
     * Send notification to all users with active subscriptions (including offline users)
     */
    public function notifyAllUsers(string $title, string $body, array $data = []): array
    {
        $users = User::whereHas('subscriptions', function($query) {
            $query->where('is_active', true);
        })->get();

        $results = [
            'total_users' => $users->count(),
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($users as $user) {
            try {
                $success = $this->webPushService->sendToUser($user, $title, $body, $data);
                if ($success) {
                    $results['successful']++;
                } else {
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = "Failed to notify user {$user->email}: " . $e->getMessage();
            }
        }

        Log::info("Bulk notification sent", [
            'title' => $title,
            'total_users' => $results['total_users'],
            'successful' => $results['successful'],
            'failed' => $results['failed']
        ]);

        return $results;
    }

    /**
     * Send urgent notification to specific user (even if offline)
     */
    public function sendUrgentNotification(User $user, string $title, string $body, array $data = []): bool
    {
        $data['priority'] = 'high';
        $data['urgent'] = true;
        
        return $this->webPushService->sendToUser($user, $title, $body, $data);
    }

    /**
     * Send payment notification to user (even if offline)
     */
    public function notifyPaymentReceived(User $user, float $amount, string $currency = 'USD'): bool
    {
        return $this->webPushService->sendPaymentNotification($user, [
            'amount' => $amount,
            'currency' => $currency,
            'id' => 'PAY_' . time()
        ]);
    }

    /**
     * Send withdrawal notification to user (even if offline)
     */
    public function notifyWithdrawalUpdate(User $user, float $amount, string $status, string $currency = 'USD'): bool
    {
        return $this->webPushService->sendWithdrawalNotification($user, [
            'amount' => $amount,
            'currency' => $currency,
            'status' => $status,
            'id' => 'WTH_' . time()
        ]);
    }

    /**
     * Send maintenance notification to all users
     */
    public function notifyMaintenance(string $message, string $scheduledTime = null): array
    {
        $title = 'System Maintenance Notice';
        $body = $scheduledTime ? 
            "Scheduled maintenance: {$message} at {$scheduledTime}" : 
            "System maintenance: {$message}";

        return $this->notifyAllUsers($title, $body, [
            'type' => 'maintenance',
            'url' => '/maintenance',
            'priority' => 'high'
        ]);
    }

    /**
     * Send new feature announcement to all users
     */
    public function announceNewFeature(string $featureName, string $description): array
    {
        $title = "New Feature: {$featureName}";
        $body = $description;

        return $this->notifyAllUsers($title, $body, [
            'type' => 'announcement',
            'url' => '/features',
            'priority' => 'normal'
        ]);
    }
}


