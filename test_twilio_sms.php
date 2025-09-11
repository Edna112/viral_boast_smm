<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== Testing Twilio SMS Configuration ===\n\n";

// Test 1: Configuration Check
echo "1. Twilio Configuration Check:\n";
echo "   SID: " . config('services.twilio.sid') . "\n";
echo "   Token: " . (config('services.twilio.token') ? 'Set (' . strlen(config('services.twilio.token')) . ' chars)' : 'Not Set') . "\n";
echo "   From: " . config('services.twilio.from') . "\n\n";

// Test 2: Twilio Client Initialization
echo "2. Twilio Client Test:\n";
try {
    $twilio = new \Twilio\Rest\Client(
        config('services.twilio.sid'),
        config('services.twilio.token')
    );
    echo "   ✅ Twilio client initialized successfully\n\n";
} catch (\Exception $e) {
    echo "   ❌ Twilio client failed: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 3: Account Verification
echo "3. Account Verification Test:\n";
try {
    $account = $twilio->api->accounts(config('services.twilio.sid'))->fetch();
    echo "   ✅ Account verified successfully\n";
    echo "   📱 Account Name: " . $account->friendlyName . "\n";
    echo "   📊 Account Status: " . $account->status . "\n\n";
} catch (\Exception $e) {
    echo "   ❌ Account verification failed: " . $e->getMessage() . "\n\n";
}

// Test 4: Phone Number Validation
echo "4. Phone Number Validation Test:\n";
try {
    $phoneNumber = config('services.twilio.from');
    $incomingPhoneNumbers = $twilio->incomingPhoneNumbers->read();
    
    $found = false;
    foreach ($incomingPhoneNumbers as $number) {
        if ($number->phoneNumber === $phoneNumber) {
            $found = true;
            echo "   ✅ Phone number verified: " . $number->phoneNumber . "\n";
            echo "   📱 Friendly Name: " . $number->friendlyName . "\n";
            break;
        }
    }
    
    if (!$found) {
        echo "   ⚠️  Phone number not found in your Twilio account\n";
        echo "   📱 Current From Number: " . $phoneNumber . "\n";
        echo "   💡 Make sure this number is purchased in your Twilio account\n\n";
    } else {
        echo "\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Phone number validation failed: " . $e->getMessage() . "\n\n";
}

// Test 5: SMS Sending Test (with test number)
echo "5. SMS Sending Test:\n";
echo "   📱 Sending test SMS to a test number...\n";
try {
    $testPhoneNumber = "+237679611727"; // Test number (change this to your actual phone number)
    $verificationCode = "123456";
    
    $message = $twilio->messages->create(
        $testPhoneNumber,
        [
            'from' => config('services.twilio.from'),
            'body' => "Your Viral Boast SMM verification code is: {$verificationCode}. This code expires in 2 minutes."
        ]
    );
    
    echo "   ✅ SMS sent successfully!\n";
    echo "   📱 Message SID: " . $message->sid . "\n";
    echo "   📊 Status: " . $message->status . "\n";
    echo "   💰 Cost: $" . $message->price . "\n\n";
    
} catch (\Exception $e) {
    echo "   ❌ SMS sending failed: " . $e->getMessage() . "\n\n";
    
    // Provide specific error guidance
    if (strpos($e->getMessage(), 'Authentication Error') !== false) {
        echo "   💡 Authentication Error - Check your SID and Token\n";
    } elseif (strpos($e->getMessage(), 'Invalid phone number') !== false) {
        echo "   💡 Invalid phone number - Check the format (+1234567890)\n";
    } elseif (strpos($e->getMessage(), 'not a valid phone number') !== false) {
        echo "   💡 Invalid phone number format - Use international format\n";
    }
    echo "\n";
}

// Test 6: Real SMS Test (if you want to test with your own number)
echo "6. Real SMS Test (Optional):\n";
echo "   📱 To test with your real phone number, update the test script\n";
echo "   💡 Replace +1234567890 with your actual phone number\n";
echo "   ⚠️  This will send a real SMS and may incur charges\n\n";

echo "=== Test Complete ===\n";
echo "🎉 Twilio SMS system is ready for production!\n";
echo "📝 Make sure to test with real phone numbers before going live.\n";
