<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

echo "Fixing super admin user email verification...\n\n";

// Find super admin user
$superAdminUser = User::where('email', 'superadmin@viralboast.com')->first();

if (!$superAdminUser) {
    echo "❌ Super admin user not found\n";
    exit(1);
}

echo "Found super admin user: " . $superAdminUser->name . "\n";
echo "Current email_verified_at: " . ($superAdminUser->email_verified_at ? $superAdminUser->email_verified_at : 'NULL') . "\n";

// Force update email verification
$superAdminUser->email_verified_at = now();
$superAdminUser->is_active = true;
$superAdminUser->save();

echo "✅ Email verification updated\n";

// Refresh and check
$superAdminUser->refresh();
echo "Updated email_verified_at: " . ($superAdminUser->email_verified_at ? $superAdminUser->email_verified_at : 'NULL') . "\n";
echo "Is active: " . ($superAdminUser->is_active ? 'Yes' : 'No') . "\n";

echo "\n✅ Super admin user verification fixed!\n";






