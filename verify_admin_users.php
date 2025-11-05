<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "Verifying existing admin users...\n";
echo str_repeat("=", 50) . "\n\n";

$admins = User::where('role', 'admin')->get();

if ($admins->count() > 0) {
    foreach ($admins as $admin) {
        $admin->update([
            'email_verified_at' => now(),
            'phone_verified_at' => now()
        ]);
        echo "✓ Verified: {$admin->name} ({$admin->email})\n";
    }
    echo "\n✅ All admin users are now verified!\n";
} else {
    echo "No admin users found.\n";
}

