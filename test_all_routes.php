<?php

// Comprehensive API Test Suite for All Routes
// Replace YOUR_DOMAIN with your actual production domain

$baseUrl = 'https://your-domain.com/api/v1'; // Replace with your actual domain
$testEmail = 'testuser' . time() . '@example.com';
$testPhone = '+1234567890';
$testPassword = 'password123';
$testName = 'Test User';

echo "🚀 COMPREHENSIVE API TEST SUITE\n";
echo "================================\n\n";

echo "📋 AUTHENTICATION ROUTES\n";
echo "========================\n\n";

echo "1️⃣ USER REGISTRATION\n";
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

echo "4️⃣ RESEND VERIFICATION\n";
echo "----------------------\n";
echo "POST: $baseUrl/auth/resend-verification\n";
echo "Body:\n";
echo json_encode([
    'email' => $testEmail
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "5️⃣ LOGIN\n";
echo "--------\n";
echo "POST: $baseUrl/auth/login\n";
echo "Body:\n";
echo json_encode([
    'email' => $testEmail,
    'password' => $testPassword
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "6️⃣ FORGOT PASSWORD\n";
echo "------------------\n";
echo "POST: $baseUrl/auth/forgot-password\n";
echo "Body:\n";
echo json_encode([
    'email' => $testEmail
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "7️⃣ VALIDATE RESET TOKEN\n";
echo "-----------------------\n";
echo "GET: $baseUrl/auth/validate-reset-token?token=reset_token&email=" . urlencode($testEmail) . "\n\n";

echo "8️⃣ RESET PASSWORD\n";
echo "-----------------\n";
echo "POST: $baseUrl/auth/reset-password\n";
echo "Body:\n";
echo json_encode([
    'token' => 'reset_token',
    'email' => $testEmail,
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "9️⃣ GET CURRENT USER\n";
echo "-------------------\n";
echo "GET: $baseUrl/auth/me\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "🔟 LOGOUT\n";
echo "--------\n";
echo "POST: $baseUrl/auth/logout\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "📋 PROFILE ROUTES\n";
echo "=================\n\n";

echo "1️⃣1️⃣ GET PROFILE\n";
echo "----------------\n";
echo "GET: $baseUrl/profile\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣2️⃣ UPDATE PROFILE\n";
echo "-------------------\n";
echo "PUT: $baseUrl/profile\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'name' => 'Updated Test User',
    'email' => $testEmail,
    'phone' => '+1987654321'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "1️⃣3️⃣ UPDATE PASSWORD\n";
echo "--------------------\n";
echo "PUT: $baseUrl/profile/password\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'current_password' => $testPassword,
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "1️⃣4️⃣ UPLOAD PROFILE PICTURE\n";
echo "---------------------------\n";
echo "POST: $baseUrl/profile/picture\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Content-Type: multipart/form-data\n";
echo "Body: profile_picture (file)\n\n";

echo "1️⃣5️⃣ DELETE PROFILE PICTURE\n";
echo "---------------------------\n";
echo "DELETE: $baseUrl/profile/picture\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣6️⃣ GET ACTIVITY HISTORY\n";
echo "-------------------------\n";
echo "GET: $baseUrl/profile/activity?page=1&limit=20\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣7️⃣ GET STATISTICS\n";
echo "-------------------\n";
echo "GET: $baseUrl/profile/stats\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣8️⃣ GET REFERRAL INFO\n";
echo "----------------------\n";
echo "GET: $baseUrl/profile/referrals\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "1️⃣9️⃣ GET PRIVACY SETTINGS\n";
echo "-------------------------\n";
echo "GET: $baseUrl/profile/privacy\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "2️⃣0️⃣ UPDATE PRIVACY SETTINGS\n";
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

echo "2️⃣1️⃣ DEACTIVATE ACCOUNT\n";
echo "------------------------\n";
echo "POST: $baseUrl/profile/deactivate\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'password' => $testPassword,
    'reason' => 'Testing account deactivation'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "📋 TASK ROUTES\n";
echo "==============\n\n";

echo "2️⃣2️⃣ GET MY TASKS\n";
echo "-----------------\n";
echo "GET: $baseUrl/tasks/my-tasks\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "2️⃣3️⃣ COMPLETE TASK\n";
echo "------------------\n";
echo "POST: $baseUrl/tasks/{assignmentId}/complete\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'proof' => 'Task completion proof',
    'notes' => 'Additional notes'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "2️⃣4️⃣ GET COMPLETION HISTORY\n";
echo "---------------------------\n";
echo "GET: $baseUrl/tasks/completion-history?page=1&limit=20\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "2️⃣5️⃣ GET TASK STATS\n";
echo "-------------------\n";
echo "GET: $baseUrl/tasks/stats\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "2️⃣6️⃣ GET TASK DETAILS\n";
echo "---------------------\n";
echo "GET: $baseUrl/tasks/{assignmentId}/details\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "📋 MEMBERSHIP ROUTES\n";
echo "====================\n\n";

echo "2️⃣7️⃣ GET ALL MEMBERSHIPS\n";
echo "------------------------\n";
echo "GET: $baseUrl/memberships\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "2️⃣8️⃣ GET MY MEMBERSHIP\n";
echo "----------------------\n";
echo "GET: $baseUrl/memberships/my-membership\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "2️⃣9️⃣ GET MEMBERSHIP HISTORY\n";
echo "---------------------------\n";
echo "GET: $baseUrl/memberships/history\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "3️⃣0️⃣ PURCHASE MEMBERSHIP\n";
echo "------------------------\n";
echo "POST: $baseUrl/memberships/purchase\n";
echo "Headers: Authorization: Bearer {token}\n";
echo "Body:\n";
echo json_encode([
    'membership_id' => 1,
    'payment_method' => 'stripe',
    'payment_token' => 'tok_1234567890'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "3️⃣1️⃣ GET MEMBERSHIP BY ID\n";
echo "-------------------------\n";
echo "GET: $baseUrl/memberships/{id}\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "3️⃣2️⃣ GET VIP COMPARISON\n";
echo "-----------------------\n";
echo "GET: $baseUrl/memberships/vip/comparison\n";
echo "Headers: Authorization: Bearer {token}\n\n";

echo "📋 ADMIN ROUTES\n";
echo "===============\n\n";

echo "3️⃣3️⃣ GET TASK CATEGORIES (Admin)\n";
echo "-------------------------------\n";
echo "GET: $baseUrl/admin/tasks/categories\n";
echo "Headers: Authorization: Bearer {admin_token}\n\n";

echo "3️⃣4️⃣ GET AVAILABLE TASKS (Admin)\n";
echo "--------------------------------\n";
echo "GET: $baseUrl/admin/tasks/available\n";
echo "Headers: Authorization: Bearer {admin_token}\n\n";

echo "3️⃣5️⃣ GET TASK STATS (Admin)\n";
echo "---------------------------\n";
echo "GET: $baseUrl/admin/tasks/stats\n";
echo "Headers: Authorization: Bearer {admin_token}\n\n";

echo "3️⃣6️⃣ ASSIGN DAILY TASKS (Admin)\n";
echo "------------------------------\n";
echo "POST: $baseUrl/admin/tasks/assign-daily\n";
echo "Headers: Authorization: Bearer {admin_token}\n";
echo "Body:\n";
echo json_encode([
    'date' => '2025-01-15',
    'user_ids' => [1, 2, 3]
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "3️⃣7️⃣ RESET DAILY TASKS (Admin)\n";
echo "-----------------------------\n";
echo "POST: $baseUrl/admin/tasks/reset-daily\n";
echo "Headers: Authorization: Bearer {admin_token}\n";
echo "Body:\n";
echo json_encode([
    'date' => '2025-01-15'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "3️⃣8️⃣ START SCHEDULER (Admin)\n";
echo "---------------------------\n";
echo "POST: $baseUrl/admin/tasks/start-scheduler\n";
echo "Headers: Authorization: Bearer {admin_token}\n\n";

echo "3️⃣9️⃣ GET ALL TASKS (Admin)\n";
echo "-------------------------\n";
echo "GET: $baseUrl/admin/tasks?page=1&limit=20\n";
echo "Headers: Authorization: Bearer {admin_token}\n\n";

echo "4️⃣0️⃣ CREATE TASK (Admin)\n";
echo "------------------------\n";
echo "POST: $baseUrl/admin/tasks\n";
echo "Headers: Authorization: Bearer {admin_token}\n";
echo "Body:\n";
echo json_encode([
    'title' => 'Test Task',
    'description' => 'This is a test task',
    'requirements' => 'Follow the instructions',
    'reward' => 50,
    'task_status' => 'active',
    'is_active' => true,
    'category_id' => 1
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "4️⃣1️⃣ GET TASK BY ID (Admin)\n";
echo "---------------------------\n";
echo "GET: $baseUrl/admin/tasks/{id}\n";
echo "Headers: Authorization: Bearer {admin_token}\n\n";

echo "4️⃣2️⃣ UPDATE TASK (Admin)\n";
echo "------------------------\n";
echo "PUT: $baseUrl/admin/tasks/{id}\n";
echo "Headers: Authorization: Bearer {admin_token}\n";
echo "Body:\n";
echo json_encode([
    'title' => 'Updated Test Task',
    'description' => 'Updated description',
    'reward' => 75,
    'task_status' => 'active'
], JSON_PRETTY_PRINT);
echo "\n\n";

echo "4️⃣3️⃣ DELETE TASK (Admin)\n";
echo "------------------------\n";
echo "DELETE: $baseUrl/admin/tasks/{id}\n";
echo "Headers: Authorization: Bearer {admin_token}\n\n";

echo "📋 TESTING CHECKLIST\n";
echo "====================\n";
echo "□ 1. Register user\n";
echo "□ 2. Get verification code\n";
echo "□ 3. Verify email\n";
echo "□ 4. Resend verification\n";
echo "□ 5. Login and get token\n";
echo "□ 6. Forgot password\n";
echo "□ 7. Validate reset token\n";
echo "□ 8. Reset password\n";
echo "□ 9. Get current user\n";
echo "□ 10. Logout\n";
echo "□ 11. Get profile\n";
echo "□ 12. Update profile\n";
echo "□ 13. Update password\n";
echo "□ 14. Upload profile picture\n";
echo "□ 15. Delete profile picture\n";
echo "□ 16. Get activity history\n";
echo "□ 17. Get statistics\n";
echo "□ 18. Get referral info\n";
echo "□ 19. Get privacy settings\n";
echo "□ 20. Update privacy settings\n";
echo "□ 21. Deactivate account\n";
echo "□ 22. Get my tasks\n";
echo "□ 23. Complete task\n";
echo "□ 24. Get completion history\n";
echo "□ 25. Get task stats\n";
echo "□ 26. Get task details\n";
echo "□ 27. Get all memberships\n";
echo "□ 28. Get my membership\n";
echo "□ 29. Get membership history\n";
echo "□ 30. Purchase membership\n";
echo "□ 31. Get membership by ID\n";
echo "□ 32. Get VIP comparison\n";
echo "□ 33. Get task categories (Admin)\n";
echo "□ 34. Get available tasks (Admin)\n";
echo "□ 35. Get task stats (Admin)\n";
echo "□ 36. Assign daily tasks (Admin)\n";
echo "□ 37. Reset daily tasks (Admin)\n";
echo "□ 38. Start scheduler (Admin)\n";
echo "□ 39. Get all tasks (Admin)\n";
echo "□ 40. Create task (Admin)\n";
echo "□ 41. Get task by ID (Admin)\n";
echo "□ 42. Update task (Admin)\n";
echo "□ 43. Delete task (Admin)\n\n";

echo "🔧 TESTING TOOLS\n";
echo "================\n";
echo "• Postman Collection\n";
echo "• Insomnia\n";
echo "• cURL commands\n";
echo "• Browser Developer Tools\n";
echo "• API testing tools\n\n";

echo "📝 IMPORTANT NOTES\n";
echo "==================\n";
echo "• Replace {token} with actual Bearer token from login\n";
echo "• Replace {admin_token} with admin user token\n";
echo "• Replace your-domain.com with your actual domain\n";
echo "• All routes require authentication except registration/verification\n";
echo "• Admin routes require admin privileges\n";
echo "• Check response status codes (200 = success, 201 = created)\n";
echo "• Verify JSON response structure\n";
echo "• Test error handling with invalid data\n\n";

echo "🎯 TESTING PRIORITIES\n";
echo "=====================\n";
echo "1. Authentication flow (register → verify → login)\n";
echo "2. Profile management (CRUD operations)\n";
echo "3. Task management (user and admin)\n";
echo "4. Membership system\n";
echo "5. Error handling and edge cases\n\n";

echo "✅ Ready to test all 43 endpoints!\n";

