<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Services\WebPushService;

echo "=== Sending Push Notification to chenwieddy@gmail.com ===\n";

// Find the user
$user = User::where('email', 'chenwieddy@gmail.com')->first();

if (!$user) {
    echo "❌ User with email chenwieddy@gmail.com not found!\n";
    exit(1);
}

echo "✅ Found user: {$user->name} ({$user->email})\n";
echo "User UUID: {$user->uuid}\n";

// Check if user has active subscriptions
$subscriptions = $user->subscriptions()->where('is_active', true)->get();

if ($subscriptions->isEmpty()) {
    echo "⚠️  No active push subscriptions found for this user.\n";
    echo "The user needs to subscribe to push notifications first.\n";
    echo "You can test the subscription by visiting the web app.\n";
    exit(1);
}

echo "✅ Found {$subscriptions->count()} active subscription(s)\n";

// Initialize WebPush service
$webPushService = new WebPushService();

echo "\n=== Sending Test Notification ===\n";
echo "📱 Sending push notification...\n";

try {
    $result = $webPushService->sendToUser(
        $user->uuid,
        'Test Notification',
        'Hello! This is a test push notification from PIS.',
        [
            'url' => '/dashboard',
            'type' => 'test',
            'timestamp' => now()->toISOString()
        ]
    );
    
    echo "✅ Notification sent successfully!\n";
    echo "📊 Results:\n";
    echo "   - Success count: {$result['success_count']}\n";
    echo "   - Failure count: {$result['failure_count']}\n";
    
    if (!empty($result['errors'])) {
        echo "   - Errors:\n";
        foreach ($result['errors'] as $error) {
            echo "     • {$error}\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Failed to send notification: " . $e->getMessage() . "\n";
}

echo "\n=== Notification Details ===\n";
echo "📱 Title: Test Notification\n";
echo "📝 Message: Hello! This is a test push notification from PIS.\n";
echo "🔗 URL: /dashboard\n";
echo "📧 User: chenwieddy@gmail.com\n";
echo "🕐 Sent at: " . now()->format('Y-m-d H:i:s') . "\n";

echo "\n=== Instructions ===\n";
echo "1. Make sure the user is logged into the web app\n";
echo "2. Check if push notifications are enabled in the browser\n";
echo "3. The notification should appear as a browser notification\n";
echo "4. Click the notification to navigate to /dashboard\n";

echo "\n=== Summary ===\n";
echo "Push notification sent to chenwieddy@gmail.com\n";
echo "Check the browser for the notification!\n";

