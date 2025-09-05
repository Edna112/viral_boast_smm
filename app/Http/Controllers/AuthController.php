<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Referral;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * Register a user using email or phone with password, then send verification code.
     */
    public function register(Request $request)
    {
        // Validate incoming registration data - either email or phone is required
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8'],
            'referralCode' => ['nullable', 'string', 'max:10'],
        ]);

        // Ensure either email or phone is provided
        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either email or phone number is required.',
                'error' => 'MissingContactInfo',
                'details' => ['field' => 'email_or_phone']
            ], 400);
        }

        // 1. Validate Duplicate User: Check if a user with the provided email or phone already exists
        if (!empty($data['email']) && User::where('email', $data['email'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A user with this email already exists.',
                'error' => 'DuplicateEmail',
                'details' => ['field' => 'email']
            ], 409);
        }

        if (!empty($data['phone']) && User::where('phone', $data['phone'])->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'A user with this phone number already exists.',
                'error' => 'DuplicatePhone',
                'details' => ['field' => 'phone']
            ], 409);
        }

        // 2. Validate Referral Code (if provided)
        $referrerId = null;
        if (!empty($data['referralCode'])) {
            $referrer = User::where('referral_code', $data['referralCode'])->first();
            if (!$referrer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid referral code provided.',
                    'error' => 'InvalidReferralCode',
                    'details' => ['field' => 'referralCode']
                ], 400);
            }
            $referrerId = $referrer->id;
        }

        // 3. Hash Password
        $hashedPassword = Hash::make($data['password']);

        // 4. Generate Referral Code for New User
        $newReferralCode = $this->generateUniqueReferralCode();

        // 5. Generate verification code
        $verificationCode = random_int(100000, 999999);

        // 6. Create User Record
        $userData = [
            'name' => $data['name'],
            'password' => $hashedPassword,
            'referral_code' => $newReferralCode,
            'referred_by' => $referrerId,
        ];

        // Add email or phone verification based on what was provided
        if (!empty($data['email'])) {
            $userData['email'] = $data['email'];
            $userData['email_verification_code'] = (string) $verificationCode;
            $userData['email_verification_expires_at'] = Carbon::now()->addMinutes(15);
        }

        if (!empty($data['phone'])) {
            $userData['phone'] = $data['phone'];
            $userData['phone_verification_code'] = (string) $verificationCode;
            $userData['phone_verification_expires_at'] = Carbon::now()->addMinutes(15);
        }

        $user = User::create($userData);

        // 7. Handle Referral (if valid referral code was provided)
        if ($referrerId) {
            Referral::create([
                'referrer_id' => $referrerId,
                'referred_user_id' => $user->id,
                'status' => 'pending',
            ]);

            // TODO: Trigger business logic for referrer (e.g., increment referral count, award points)
            // This should be handled asynchronously via a queue to avoid slowing down registration
        }

        // 8. Send verification code
        if (!empty($data['email'])) {
            // Send verification code via email
            Mail::raw("Your verification code is: {$verificationCode}", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Verification Code');
            });
            $verificationMessage = 'Please check your email to verify your account.';
        } else {
            // Send verification code via SMS (placeholder - implement SMS service)
            $this->sendSMS($user->phone, "Your verification code is: {$verificationCode}");
            $verificationMessage = 'Please check your phone for the verification code.';
        }

        // 9. Response
        $responseData = [
            'userId' => $user->id,
            'referralCode' => $newReferralCode
        ];

        if (!empty($data['email'])) {
            $responseData['email'] = $user->email;
        }
        if (!empty($data['phone'])) {
            $responseData['phone'] = $user->phone;
        }

        return response()->json([
            'success' => true,
            'message' => "Registration successful. {$verificationMessage}",
            'data' => $responseData
        ], 201);
    }

    /**
     * Verify the 6-digit code for email or phone and mark as verified.
     */
    public function verify(Request $request)
    {
        // Validate payload - either email or phone is required
        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'code' => ['required', 'string'],
        ]);

        // Ensure either email or phone is provided
        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either email or phone number is required.',
                'error' => 'MissingContactInfo'
            ], 400);
        }

        // Look up the user by email or phone
        $user = null;
        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
        } else {
            $user = User::where('phone', $data['phone'])->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Determine verification type and check if already verified
        $isEmailVerification = !empty($data['email']);
        $isPhoneVerification = !empty($data['phone']);

        if ($isEmailVerification && $user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified'
            ], 200);
        }

        if ($isPhoneVerification && $user->phone_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Phone already verified'
            ], 200);
        }

        // Validate code and its expiration
        $isValidCode = false;
        $updateData = [];

        if ($isEmailVerification) {
            if (
                $user->email_verification_code &&
                $user->email_verification_expires_at &&
                Carbon::now()->lessThanOrEqualTo($user->email_verification_expires_at) &&
                $user->email_verification_code === $data['code']
            ) {
                $isValidCode = true;
                $updateData = [
                    'email_verified_at' => Carbon::now(),
                    'email_verification_code' => null,
                    'email_verification_expires_at' => null,
                ];
            }
        } else {
            if (
                $user->phone_verification_code &&
                $user->phone_verification_expires_at &&
                Carbon::now()->lessThanOrEqualTo($user->phone_verification_expires_at) &&
                $user->phone_verification_code === $data['code']
            ) {
                $isValidCode = true;
                $updateData = [
                    'phone_verified_at' => Carbon::now(),
                    'phone_verification_code' => null,
                    'phone_verification_expires_at' => null,
                ];
            }
        }

        if (!$isValidCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired code'
            ], 422);
        }

        // Mark as verified and clear code fields
        $user->forceFill($updateData)->save();

        $verificationType = $isEmailVerification ? 'Email' : 'Phone';
        return response()->json([
            'success' => true,
            'message' => "{$verificationType} verified successfully"
        ]);
    }

    /**
     * Resend verification code for email or phone if previous one expired.
     */
    public function resendVerification(Request $request)
    {
        // Validate payload - either email or phone is required
        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
        ]);

        // Ensure either email or phone is provided
        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either email or phone number is required.',
                'error' => 'MissingContactInfo'
            ], 400);
        }

        // Look up the user by email or phone
        $user = null;
        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
        } else {
            $user = User::where('phone', $data['phone'])->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Determine verification type and check if already verified
        $isEmailVerification = !empty($data['email']);
        $isPhoneVerification = !empty($data['phone']);

        if ($isEmailVerification && $user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified'
            ], 200);
        }

        if ($isPhoneVerification && $user->phone_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Phone already verified'
            ], 200);
        }

        // Check if there's an active, unexpired code
        $expiresAtField = $isEmailVerification ? 'email_verification_expires_at' : 'phone_verification_expires_at';
        if ($user->$expiresAtField && Carbon::now()->lessThan($user->$expiresAtField)) {
            // Compute remaining seconds to wait
            $remaining = Carbon::now()->diffInSeconds($user->$expiresAtField, false);
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting a new code',
                'retry_after_seconds' => max($remaining, 0),
            ], 429);
        }

        // Generate new code with a new 15-minute expiration
        $verificationCode = random_int(100000, 999999);
        $updateData = [];

        if ($isEmailVerification) {
            $updateData = [
                'email_verification_code' => (string) $verificationCode,
                'email_verification_expires_at' => Carbon::now()->addMinutes(2),
            ];
        } else {
            $updateData = [
                'phone_verification_code' => (string) $verificationCode,
                'phone_verification_expires_at' => Carbon::now()->addMinutes(2),
            ];
        }

        $user->forceFill($updateData)->save();

        // Send the new verification code
        if ($isEmailVerification) {
            Mail::raw("Your verification code is: {$verificationCode}", function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Verification Code');
            });
            $message = 'A new verification code has been sent to your email.';
        } else {
            $this->sendSMS($user->phone, "Your verification code is: {$verificationCode}");
            $message = 'A new verification code has been sent to your phone.';
        }

        return response()->json([
            'success' => true,
            'message' => $message
        ]);
    }

    /**
     * Login with email or phone and password, return Sanctum token and user profile.
     */
    public function login(Request $request)
    {
        // Validate login payload - either email or phone is required
        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Ensure either email or phone is provided
        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either email or phone number is required.',
                'error' => 'MissingContactInfo'
            ], 400);
        }

        // Attempt to find user by email or phone
        $user = null;
        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])->first();
        } else {
            $user = User::where('phone', $data['phone'])->first();
        }

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Create API token for the session
        $token = $user->createToken('api')->plainTextToken;

        // Respond with token and basic user info
        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'referralCode' => $user->referral_code,
                'emailVerified' => !is_null($user->email_verified_at),
                'phoneVerified' => !is_null($user->phone_verified_at),
            ],
        ]);
    }

    /**
     * Register a user using phone number and password (no SMS verification).
     */
    public function registerPhone(Request $request)
    {
        // Validate required fields for phone-based registration
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        // Create user with phone as primary identifier, email remains optional/null
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
        ]);

        return response()->json([
            'message' => 'Registration via phone successful.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
            ],
        ], 201);
    }

    /**
     * Login using phone number and password.
     */
    public function loginPhone(Request $request)
    {
        // Validate login payload
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Attempt to find user by phone and verify password
        $user = User::where('phone', $data['phone'])->first();
        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Create API token for the session
        $token = $user->createToken('api')->plainTextToken;

        // Respond with token and basic user info
        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
            ],
        ]);
    }

    /**
     * Send password reset link to email using Password Broker.
     */
    public function forgot(Request $request)
    {
        // Validate email input
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Use Laravel's Password Broker to send reset link
        $status = app('auth.password.broker')->sendResetLink(['email' => $data['email']]);

        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset link sent'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Unable to send reset link'
        ], 422);
    }

    /**
     * Validate a reset token from a password reset link.
     */
    public function validateResetToken(Request $request)
    {
        // Validate token and email input
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
        ]);

        // Check if the token is valid using Laravel's Password Broker
        $user = \App\Models\User::where('email', $data['email'])->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }

        // Check if token exists and is valid
        $tokenExists = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $data['email'])
            ->where('token', \Illuminate\Support\Facades\Hash::make($data['token']))
            ->where('created_at', '>', now()->subHours(1)) // Token expires after 1 hour
            ->exists();

        if ($tokenExists) {
            return response()->json([
                'success' => true,
                'message' => 'Reset token is valid'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired reset token'
        ], 422);
    }

    /**
     * Reset password using token, then rotate remember token and fire event.
     */
    public function reset(Request $request)
    {
        // Validate reset payload
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Attempt the password reset using the broker
        $status = app('auth.password.broker')->reset(
            [
                'email' => $data['email'],
                'password' => $data['password'],
                'password_confirmation' => $request->input('password_confirmation'),
                'token' => $data['token'],
            ],
            function (User $user, string $password) {
                // Persist new password and rotate remember token
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                // Notify Laravel listeners of password reset
                event(new PasswordReset($user));
            }
        );

        if ($status === \Illuminate\Support\Facades\Password::PASSWORD_RESET) {
            return response()->json([
                'success' => true,
                'message' => 'Password reset successful'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid token or email'
        ], 422);
    }

    /**
     * Return the authenticated user profile.
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
     * Revoke the current access token for the authenticated user.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Generate a unique referral code for new users.
     */
    private function generateUniqueReferralCode(): string
    {
        do {
            // Generate a random 8-character alphanumeric code
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    /**
     * Send SMS verification code (placeholder implementation).
     * Replace this with your actual SMS service integration.
     */
    private function sendSMS(string $phone, string $message): void
    {
        // TODO: Implement actual SMS service integration
        // For now, just log the SMS (in production, integrate with services like Twilio, AWS SNS, etc.)
        \Log::info("SMS to {$phone}: {$message}");
        
        // Example integration with Twilio:
        // $twilio = new \Twilio\Rest\Client(config('services.twilio.sid'), config('services.twilio.token'));
        // $twilio->messages->create($phone, ['from' => config('services.twilio.from'), 'body' => $message]);
    }
}


