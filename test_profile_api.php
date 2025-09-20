<?php

require_once 'vendor/autoload.php';

use Illuminate\Support\Facades\Http;

// Configuration
$baseUrl = 'http://localhost:8000/api/v1';
$testEmail = 'testuser' . time() . '@example.com';
$testPhone = '+1234567890';
$testPassword = 'password123';
$testName = 'Test User';

echo "🚀 Starting Profile API Test Suite\n";
echo "=====================================\n\n";

// Helper function to make API calls
function makeRequest($method, $url, $data = [], $token = null) {
    $headers = ['Accept' => 'application/json'];
    if ($token) {
        $headers['Authorization'] = 'Bearer ' . $token;
    }
    
    $response = Http::withHeaders($headers)->$method($url, $data);
    
    echo "📡 $method $url\n";
    echo "Status: " . $response->status() . "\n";
    echo "Response: " . $response->body() . "\n\n";
    
    return $response;
}

// Test 1: Register a new user
echo "1️⃣ Testing User Registration\n";
echo "-----------------------------\n";
$registerData = [
    'name' => $testName,
    'email' => $testEmail,
    'phone' => $testPhone,
    'password' => $testPassword,
    'password_confirmation' => $testPassword
];

$registerResponse = makeRequest('post', "$baseUrl/auth/register", $registerData);

if ($registerResponse->successful()) {
    $registerData = $registerResponse->json();
    echo "✅ Registration successful!\n";
    echo "User ID: " . $registerData['data']['userId'] . "\n";
    echo "Email: " . $registerData['data']['email'] . "\n\n";
} else {
    echo "❌ Registration failed!\n";
    exit;
}

// Test 2: Get verification code (development only)
echo "2️⃣ Getting Verification Code (Development)\n";
echo "------------------------------------------\n";
$verificationResponse = makeRequest('get', "$baseUrl/auth/verification-code?email=$testEmail");

if ($verificationResponse->successful()) {
    $verificationData = $verificationResponse->json();
    $verificationCode = $verificationData['data']['code'];
    echo "✅ Verification code retrieved: $verificationCode\n\n";
} else {
    echo "❌ Failed to get verification code!\n";
    exit;
}

// Test 3: Verify email
echo "3️⃣ Verifying Email\n";
echo "------------------\n";
$verifyData = [
    'email' => $testEmail,
    'code' => $verificationCode
];

$verifyResponse = makeRequest('post', "$baseUrl/auth/verify-email", $verifyData);

if ($verifyResponse->successful()) {
    echo "✅ Email verified successfully!\n\n";
} else {
    echo "❌ Email verification failed!\n";
    exit;
}

// Test 4: Login to get token
echo "4️⃣ Logging In\n";
echo "-------------\n";
$loginData = [
    'email' => $testEmail,
    'password' => $testPassword
];

$loginResponse = makeRequest('post', "$baseUrl/auth/login", $loginData);

if ($loginResponse->successful()) {
    $loginData = $loginResponse->json();
    $token = $loginData['token'];
    echo "✅ Login successful!\n";
    echo "Token: " . substr($token, 0, 20) . "...\n\n";
} else {
    echo "❌ Login failed!\n";
    exit;
}

// Test 5: Get Profile
echo "5️⃣ Getting Profile\n";
echo "------------------\n";
$profileResponse = makeRequest('get', "$baseUrl/profile", [], $token);

if ($profileResponse->successful()) {
    echo "✅ Profile retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get profile!\n";
}

// Test 6: Update Profile
echo "6️⃣ Updating Profile\n";
echo "-------------------\n";
$updateData = [
    'name' => 'Updated Test User',
    'email' => $testEmail, // Keep same email
    'phone' => '+1987654321'
];

$updateResponse = makeRequest('put', "$baseUrl/profile", $updateData, $token);

if ($updateResponse->successful()) {
    echo "✅ Profile updated successfully!\n\n";
} else {
    echo "❌ Failed to update profile!\n";
}

// Test 7: Update Password
echo "7️⃣ Updating Password\n";
echo "--------------------\n";
$passwordData = [
    'current_password' => $testPassword,
    'password' => 'newpassword123',
    'password_confirmation' => 'newpassword123'
];

$passwordResponse = makeRequest('put', "$baseUrl/profile/password", $passwordData, $token);

if ($passwordResponse->successful()) {
    echo "✅ Password updated successfully!\n\n";
} else {
    echo "❌ Failed to update password!\n";
}

// Test 8: Get Activity History
echo "8️⃣ Getting Activity History\n";
echo "---------------------------\n";
$activityResponse = makeRequest('get', "$baseUrl/profile/activity", [], $token);

if ($activityResponse->successful()) {
    echo "✅ Activity history retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get activity history!\n";
}

// Test 9: Get Statistics
echo "9️⃣ Getting Statistics\n";
echo "---------------------\n";
$statsResponse = makeRequest('get', "$baseUrl/profile/stats", [], $token);

if ($statsResponse->successful()) {
    echo "✅ Statistics retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get statistics!\n";
}

// Test 10: Get Referral Info
echo "🔟 Getting Referral Info\n";
echo "------------------------\n";
$referralResponse = makeRequest('get', "$baseUrl/profile/referrals", [], $token);

if ($referralResponse->successful()) {
    echo "✅ Referral info retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get referral info!\n";
}

// Test 11: Get Privacy Settings
echo "1️⃣1️⃣ Getting Privacy Settings\n";
echo "------------------------------\n";
$privacyResponse = makeRequest('get', "$baseUrl/profile/privacy", [], $token);

if ($privacyResponse->successful()) {
    echo "✅ Privacy settings retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get privacy settings!\n";
}

// Test 12: Update Privacy Settings
echo "1️⃣2️⃣ Updating Privacy Settings\n";
echo "-------------------------------\n";
$privacyUpdateData = [
    'profile_visibility' => 'friends',
    'show_email' => true,
    'show_phone' => false,
    'show_activity' => true,
    'email_notifications' => true,
    'sms_notifications' => false
];

$privacyUpdateResponse = makeRequest('put', "$baseUrl/profile/privacy", $privacyUpdateData, $token);

if ($privacyUpdateResponse->successful()) {
    echo "✅ Privacy settings updated successfully!\n\n";
} else {
    echo "❌ Failed to update privacy settings!\n";
}

// Test 13: Test Profile Picture Upload (simulated)
echo "1️⃣3️⃣ Testing Profile Picture Upload\n";
echo "------------------------------------\n";
echo "Note: This would require a real image file upload\n";
echo "Skipping actual file upload test...\n\n";

// Test 14: Test Profile Picture Delete
echo "1️⃣4️⃣ Testing Profile Picture Delete\n";
echo "------------------------------------\n";
$deletePictureResponse = makeRequest('delete', "$baseUrl/profile/picture", [], $token);

if ($deletePictureResponse->successful()) {
    echo "✅ Profile picture delete test successful!\n\n";
} else {
    echo "❌ Failed to delete profile picture!\n";
}

// Test 15: Final Profile Check
echo "1️⃣5️⃣ Final Profile Check\n";
echo "-------------------------\n";
$finalProfileResponse = makeRequest('get', "$baseUrl/profile", [], $token);

if ($finalProfileResponse->successful()) {
    $finalData = $finalProfileResponse->json();
    echo "✅ Final profile check successful!\n";
    echo "User Name: " . $finalData['data']['user']['name'] . "\n";
    echo "Email: " . $finalData['data']['user']['email'] . "\n";
    echo "Phone: " . $finalData['data']['user']['phone'] . "\n";
    echo "Total Points: " . $finalData['data']['user']['total_points'] . "\n";
    echo "Profile Visibility: " . $finalData['data']['user']['profile_visibility'] . "\n\n";
} else {
    echo "❌ Final profile check failed!\n";
}

// Test 16: Logout
echo "1️⃣6️⃣ Logging Out\n";
echo "-----------------\n";
$logoutResponse = makeRequest('post', "$baseUrl/auth/logout", [], $token);

if ($logoutResponse->successful()) {
    echo "✅ Logout successful!\n\n";
} else {
    echo "❌ Logout failed!\n";
}

echo "🎉 Profile API Test Suite Complete!\n";
echo "=====================================\n";
echo "All major profile endpoints have been tested.\n";
echo "Check the responses above for any errors.\n";

