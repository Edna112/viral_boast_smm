<?php

// Manual Profile API Test
// Replace YOUR_DOMAIN with your actual production domain

$baseUrl = 'https://your-domain.com/api/v1'; // Replace with your actual domain
$testEmail = 'testuser' . time() . '@example.com';
$testPhone = '+1234567890';
$testPassword = 'password123';
$testName = 'Test User';

echo "🚀 Manual Profile API Test Instructions\n";
echo "=======================================\n\n";

echo "1️⃣ REGISTRATION TEST\n";
echo "--------------------\n";
echo "POST: $baseUrl/auth/register\n";
echo "Body:\n";
echo json_encode([
    'name' => $testName,
    'email' => $testEmail,
    'phone' => $testPhone,
    'password' => $testPassword,
    'password_confirmation' => $testPassword
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "2️⃣ GET VERIFICATION CODE (Development)\n";
echo "--------------------------------------\n";
echo "GET: $baseUrl/auth/verification-code?email=" . urlencode($testEmail) . "\n\n";

echo "3️⃣ VERIFY EMAIL\n";
echo "---------------\n";
echo "POST: $baseUrl/auth/verify-email\n";
echo "Body:\n";
echo json_encode([
    'email' => $testEmail,
    'code' => '123456' // Replace with actual code
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "4️⃣ LOGIN\n";
echo "--------\n";
echo "POST: $baseUrl/auth/login\n";
echo "Body:\n";
echo json_encode([
    'email' => $testEmail,
    'password' => $testPassword
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "5️⃣ GET PROFILE\n";
echo "--------------\n";
echo "GET: $baseUrl/profile\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "6️⃣ UPDATE PROFILE\n";
echo "-----------------\n";
echo "PUT: $baseUrl/profile\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'name' => 'Updated Test User',
    'email' => $testEmail,
    'phone' => '+1987654321'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "7️⃣ UPDATE PASSWORD\n";
echo "------------------\n";
echo "PUT: $baseUrl/profile/password\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'current_password' => $testPassword,
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "8️⃣ GET ACTIVITY HISTORY\n";
echo "-----------------------\n";
echo "GET: $baseUrl/profile/activity\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "9️⃣ GET STATISTICS\n";
echo "-----------------\n";
echo "GET: $baseUrl/profile/stats\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "🔟 GET REFERRAL INFO\n";
echo "-------------------\n";
echo "GET: $baseUrl/profile/referrals\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣1️⃣ GET PRIVACY SETTINGS\n";
echo "-------------------------\n";
echo "GET: $baseUrl/profile/privacy\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣2️⃣ UPDATE PRIVACY SETTINGS\n";
echo "----------------------------\n";
echo "PUT: $baseUrl/profile/privacy\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'profile_visibility' => 'friends',
    'show_email' => true,
    'show_phone' => false,
    'show_activity' => true,
    'email_notifications' => true,
    'sms_notifications' => false
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "1️⃣3️⃣ DELETE PROFILE PICTURE\n";
echo "---------------------------\n";
echo "DELETE: $baseUrl/profile/picture\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣4️⃣ LOGOUT\n";
echo "-----------\n";
echo "POST: $baseUrl/auth/logout\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "📋 TESTING CHECKLIST\n";
echo "====================\n";
echo "□ 1. Register user\n";
echo "□ 2. Get verification code\n";
echo "□ 3. Verify email\n";
echo "□ 4. Login and get token\n";
echo "□ 5. Get profile\n";
echo "□ 6. Update profile\n";
echo "□ 7. Update password\n";
echo "□ 8. Get activity history\n";
echo "□ 9. Get statistics\n";
echo "□ 10. Get referral info\n";
echo "□ 11. Get privacy settings\n";
echo "□ 12. Update privacy settings\n";
echo "□ 13. Delete profile picture\n";
echo "□ 14. Logout\n\n";

echo "🔧 TOOLS TO USE\n";
echo "===============\n";
echo "• Postman\n";
echo "• Insomnia\n";
echo "• cURL commands\n";
echo "• Browser Developer Tools\n";
echo "• API testing tools\n\n";

echo "📝 NOTES\n";
echo "========\n";
echo "• Replace {token} with actual Bearer token from login\n";
echo "• Replace your-domain.com with your actual domain\n";
echo "• All profile routes require authentication\n";
echo "• Check response status codes (200 = success)\n";
echo "• Verify JSON response structure\n\n";

echo "✅ Ready to test! Use the URLs and data above.\n";

