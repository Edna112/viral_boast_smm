<?php

// Configuration
$baseUrl = 'http://localhost:8000/api/v1';
$testEmail = 'testuser' . time() . '@example.com';
$testPhone = '+1234567890';
$testPassword = 'password123';
$testName = 'Test User';

echo "🚀 Starting Profile API Test Suite with cURL\n";
echo "============================================\n\n";

// Helper function to make cURL requests
function makeCurlRequest($method, $url, $data = [], $token = null) {
    $ch = curl_init();
    
    $headers = [
        'Accept: application/json',
        'Content-Type: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_POSTFIELDS => !empty($data) ? json_encode($data) : null,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "📡 $method $url\n";
    echo "Status: $httpCode\n";
    echo "Response: $response\n";
    if ($error) {
        echo "Error: $error\n";
    }
    echo "\n";
    
    return [
        'status' => $httpCode,
        'body' => $response,
        'error' => $error
    ];
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

$registerResponse = makeCurlRequest('POST', "$baseUrl/auth/register", $registerData);

if ($registerResponse['status'] == 201) {
    $registerData = json_decode($registerResponse['body'], true);
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
$verificationResponse = makeCurlRequest('GET', "$baseUrl/auth/verification-code?email=" . urlencode($testEmail));

if ($verificationResponse['status'] == 200) {
    $verificationData = json_decode($verificationResponse['body'], true);
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

$verifyResponse = makeCurlRequest('POST', "$baseUrl/auth/verify-email", $verifyData);

if ($verifyResponse['status'] == 200) {
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

$loginResponse = makeCurlRequest('POST', "$baseUrl/auth/login", $loginData);

if ($loginResponse['status'] == 200) {
    $loginData = json_decode($loginResponse['body'], true);
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
$profileResponse = makeCurlRequest('GET', "$baseUrl/profile", [], $token);

if ($profileResponse['status'] == 200) {
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

$updateResponse = makeCurlRequest('PUT', "$baseUrl/profile", $updateData, $token);

if ($updateResponse['status'] == 200) {
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

$passwordResponse = makeCurlRequest('PUT', "$baseUrl/profile/password", $passwordData, $token);

if ($passwordResponse['status'] == 200) {
    echo "✅ Password updated successfully!\n\n";
} else {
    echo "❌ Failed to update password!\n";
}

// Test 8: Get Activity History
echo "8️⃣ Getting Activity History\n";
echo "---------------------------\n";
$activityResponse = makeCurlRequest('GET', "$baseUrl/profile/activity", [], $token);

if ($activityResponse['status'] == 200) {
    echo "✅ Activity history retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get activity history!\n";
}

// Test 9: Get Statistics
echo "9️⃣ Getting Statistics\n";
echo "---------------------\n";
$statsResponse = makeCurlRequest('GET', "$baseUrl/profile/stats", [], $token);

if ($statsResponse['status'] == 200) {
    echo "✅ Statistics retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get statistics!\n";
}

// Test 10: Get Referral Info
echo "🔟 Getting Referral Info\n";
echo "------------------------\n";
$referralResponse = makeCurlRequest('GET', "$baseUrl/profile/referrals", [], $token);

if ($referralResponse['status'] == 200) {
    echo "✅ Referral info retrieved successfully!\n\n";
} else {
    echo "❌ Failed to get referral info!\n";
}

// Test 11: Get Privacy Settings
echo "1️⃣1️⃣ Getting Privacy Settings\n";
echo "------------------------------\n";
$privacyResponse = makeCurlRequest('GET', "$baseUrl/profile/privacy", [], $token);

if ($privacyResponse['status'] == 200) {
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

$privacyUpdateResponse = makeCurlRequest('PUT', "$baseUrl/profile/privacy", $privacyUpdateData, $token);

if ($privacyUpdateResponse['status'] == 200) {
    echo "✅ Privacy settings updated successfully!\n\n";
} else {
    echo "❌ Failed to update privacy settings!\n";
}

// Test 13: Test Profile Picture Delete
echo "1️⃣3️⃣ Testing Profile Picture Delete\n";
echo "------------------------------------\n";
$deletePictureResponse = makeCurlRequest('DELETE', "$baseUrl/profile/picture", [], $token);

if ($deletePictureResponse['status'] == 200) {
    echo "✅ Profile picture delete test successful!\n\n";
} else {
    echo "❌ Failed to delete profile picture!\n";
}

// Test 14: Final Profile Check
echo "1️⃣4️⃣ Final Profile Check\n";
echo "-------------------------\n";
$finalProfileResponse = makeCurlRequest('GET', "$baseUrl/profile", [], $token);

if ($finalProfileResponse['status'] == 200) {
    $finalData = json_decode($finalProfileResponse['body'], true);
    echo "✅ Final profile check successful!\n";
    echo "User Name: " . $finalData['data']['user']['name'] . "\n";
    echo "Email: " . $finalData['data']['user']['email'] . "\n";
    echo "Phone: " . $finalData['data']['user']['phone'] . "\n";
    echo "Total Points: " . $finalData['data']['user']['total_points'] . "\n";
    echo "Profile Visibility: " . $finalData['data']['user']['profile_visibility'] . "\n\n";
} else {
    echo "❌ Final profile check failed!\n";
}

// Test 15: Logout
echo "1️⃣5️⃣ Logging Out\n";
echo "-----------------\n";
$logoutResponse = makeCurlRequest('POST', "$baseUrl/auth/logout", [], $token);

if ($logoutResponse['status'] == 200) {
    echo "✅ Logout successful!\n\n";
} else {
    echo "❌ Logout failed!\n";
}

echo "🎉 Profile API Test Suite Complete!\n";
echo "=====================================\n";
echo "All major profile endpoints have been tested.\n";
echo "Check the responses above for any errors.\n";

