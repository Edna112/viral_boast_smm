<?php

namespace App\Services;

use App\Models\UserSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class WebPushService
{
    private $vapidPublicKey;
    private $vapidPrivateKey;
    private $vapidSubject;

    public function __construct()
    {
        $this->vapidPublicKey = config('webpush.vapid.public_key') ?: '';
        $this->vapidPrivateKey = config('webpush.vapid.private_key') ?: '';
        $this->vapidSubject = config('webpush.vapid.subject') ?: 'mailto:admin@viralboast.com';
    }

    /**
     * Send notification to multiple subscriptions (batch processing)
     */
    public function sendToMultipleSubscriptions(array $subscriptions, string $title, string $body, array $data = []): array
    {
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'icon' => '/icon-192x192.png',
            'badge' => '/badge-72x72.png',
            'url' => $data['url'] ?? '/'
        ]);

        $webPush = new \Minishlink\WebPush\WebPush([
            'VAPID' => [
                'subject' => $this->vapidSubject,
                'publicKey' => $this->vapidPublicKey,
                'privateKey' => $this->vapidPrivateKey,
            ],
        ]);

        $results = [
            'success_count' => 0,
            'failure_count' => 0,
            'errors' => []
        ];

        foreach ($subscriptions as $subscription) {
            try {
                // Handle both array and object formats
                $endpoint = is_array($subscription) ? $subscription['endpoint'] : $subscription->endpoint;
                $publicKey = is_array($subscription) ? $subscription['public_key'] : $subscription->public_key;
                $authToken = is_array($subscription) ? $subscription['auth_token'] : $subscription->auth_token;
                $subscriptionId = is_array($subscription) ? ($subscription['id'] ?? 'unknown') : $subscription->id;
                
                // Check if this is a test subscription
                if ($endpoint === 'https://fcm.googleapis.com/fcm/send/test-endpoint' || 
                    $publicKey === 'test-public-key' || 
                    $authToken === 'test-auth-token') {
                    
                    Log::info("Test Web Push Notification (simulated)", [
                        'subscription_id' => $subscriptionId,
                        'endpoint' => $endpoint,
                        'title' => $title,
                        'body' => $body
                    ]);
                    $results['success_count']++;
                    continue;
                }
                
                $webPush->queueNotification(
                    \Minishlink\WebPush\Subscription::create([
                        'endpoint' => $endpoint,
                        'keys' => [
                            'p256dh' => $publicKey,
                            'auth' => $authToken,
                        ],
                    ]),
                    $payload
                );
            } catch (\Exception $e) {
                $results['failure_count']++;
                $results['errors'][] = "Failed to queue notification for subscription {$subscriptionId}: " . $e->getMessage();
            }
        }

        // Send all queued notifications
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $results['success_count']++;
            } else {
                $results['failure_count']++;
                $results['errors'][] = "Notification failed: " . $report->getReason();
            }
        }

        return $results;
    }

    /**
     * Send notification to a specific user
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): bool
    {
        $subscriptions = UserSubscription::where('user_uuid', $user->uuid)
            ->where('is_active', true)
            ->get();

        if ($subscriptions->isEmpty()) {
            Log::info("No active subscriptions found for user: {$user->uuid}");
            return false;
        }

        // Use batch processing for better performance
        $results = $this->sendToMultipleSubscriptions($subscriptions->toArray(), $title, $body, $data);

        Log::info("Sent push notification to user {$user->uuid}: {$results['success_count']}/{$subscriptions->count()} subscriptions successful");
        
        if (!empty($results['errors'])) {
            Log::warning("Some notifications failed for user {$user->uuid}", [
                'errors' => $results['errors']
            ]);
        }

        return $results['success_count'] > 0;
    }

    /**
     * Send notification to a specific subscription
     */
    public function sendToSubscription(UserSubscription $subscription, string $title, string $body, array $data = []): bool
    {
        try {
            $payload = json_encode([
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'icon' => '/icon-192x192.png',
                'badge' => '/badge-72x72.png',
                'url' => $data['url'] ?? '/'
            ]);

            // Check if this is a test subscription with invalid keys
            if ($subscription->endpoint === 'https://fcm.googleapis.com/fcm/send/test-endpoint' || 
                $subscription->public_key === 'test-public-key' || 
                $subscription->auth_token === 'test-auth-token') {
                
                Log::info("Test Web Push Notification (simulated)", [
                    'subscription_id' => $subscription->id,
                    'endpoint' => $subscription->endpoint,
                    'title' => $title,
                    'body' => $body,
                    'payload' => $payload
                ]);
                return true;
            }

            // Use minishlink/web-push library for real subscriptions
            $webPush = new \Minishlink\WebPush\WebPush([
                'VAPID' => [
                    'subject' => $this->vapidSubject,
                    'publicKey' => $this->vapidPublicKey,
                    'privateKey' => $this->vapidPrivateKey,
                ],
            ]);

            $report = $webPush->sendOneNotification(
                \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'keys' => [
                        'p256dh' => $subscription->public_key,
                        'auth' => $subscription->auth_token,
                    ],
                ]),
                $payload
            );

            if ($report->isSuccess()) {
                Log::info("Web Push Notification sent successfully", [
                    'subscription_id' => $subscription->id,
                    'endpoint' => $subscription->endpoint,
                    'title' => $title,
                    'body' => $body
                ]);
                return true;
            } else {
                Log::error("Web Push Notification failed", [
                    'subscription_id' => $subscription->id,
                    'endpoint' => $subscription->endpoint,
                    'error' => $report->getReason(),
                    'response' => $report->getResponse()
                ]);
                return false;
            }

        } catch (\Exception $e) {
            Log::error("Failed to send push notification", [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send payment notification
     */
    public function sendPaymentNotification(User $user, array $paymentData): bool
    {
        $title = "Payment Received";
        $body = "You received {$paymentData['amount']} {$paymentData['currency']}";
        $data = [
            'url' => '/payments',
            'type' => 'payment',
            'payment_id' => $paymentData['id'] ?? null
        ];

        return $this->sendToUser($user, $title, $body, $data);
    }

    /**
     * Send withdrawal notification
     */
    public function sendWithdrawalNotification(User $user, array $withdrawalData): bool
    {
        $title = "Withdrawal Update";
        $body = "Your withdrawal of {$withdrawalData['amount']} {$withdrawalData['currency']} is {$withdrawalData['status']}";
        $data = [
            'url' => '/withdrawals',
            'type' => 'withdrawal',
            'withdrawal_id' => $withdrawalData['id'] ?? null
        ];

        return $this->sendToUser($user, $title, $body, $data);
    }

    /**
     * Send task notification
     */
    public function sendTaskNotification(User $user, array $taskData): bool
    {
        $title = "New Task Assigned";
        $body = "You have been assigned a new task: {$taskData['title']}";
        $data = [
            'url' => '/tasks',
            'type' => 'task',
            'task_id' => $taskData['id'] ?? null
        ];

        return $this->sendToUser($user, $title, $body, $data);
    }

    /**
     * Get VAPID public key for frontend
     */
    public function getVapidPublicKey(): string
    {
        if (empty($this->vapidPublicKey)) {
            // Log the issue for debugging
            Log::error('VAPID public key is empty', [
                'config_value' => config('webpush.vapid.public_key'),
                'env_value' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
                'config_file_exists' => file_exists(config_path('webpush.php')),
                'env_file_exists' => file_exists(base_path('.env'))
            ]);
            
            throw new \Exception('VAPID public key is not configured. Please set WEBPUSH_VAPID_PUBLIC_KEY in your .env file.');
        }
        return $this->vapidPublicKey;
    }

    /**
     * Check if VAPID keys are configured
     */
    public function isVapidConfigured(): bool
    {
        return !empty($this->vapidPublicKey) && !empty($this->vapidPrivateKey);
    }

    /**
     * Get VAPID configuration status for debugging
     */
    public function getVapidStatus(): array
    {
        return [
            'public_key_configured' => !empty($this->vapidPublicKey),
            'private_key_configured' => !empty($this->vapidPrivateKey),
            'subject_configured' => !empty($this->vapidSubject),
            'config_file_exists' => file_exists(config_path('webpush.php')),
            'env_file_exists' => file_exists(base_path('.env')),
            'env_public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
            'env_private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
            'config_public_key' => config('webpush.vapid.public_key'),
            'config_private_key' => config('webpush.vapid.private_key'),
        ];
    }
}
