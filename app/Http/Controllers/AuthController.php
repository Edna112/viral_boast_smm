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

        // 2. Validate Referral Code (if provided) with limits check
        $referrer = null;
        if (!empty($data['referralCode'])) {
            $validation = User::validateReferralCodeWithLimits($data['referralCode']);
            if (!$validation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => $validation['message'],
                    'error' => $validation['error'],
                    'details' => ['field' => 'referralCode']
                ], 400);
            }
            $referrer = User::getByReferralCode($data['referralCode']);
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
            'referred_by' => $referrer ? $referrer->uuid : null,
        ];

        // Add email or phone verification based on what was provided
        if (!empty($data['email'])) {
            $userData['email'] = $data['email'];
            $userData['email_verification_code'] = (string) $verificationCode;
            $userData['email_verification_expires_at'] = Carbon::now()->addMinutes(2);
        }

        if (!empty($data['phone'])) {
            $userData['phone'] = $data['phone'];
            $userData['phone_verification_code'] = (string) $verificationCode;
            $userData['phone_verification_expires_at'] = Carbon::now()->addMinutes(2);
        }

        $user = User::create($userData);

        // 7. Assign basic membership by default
        $this->assignBasicMembershipToUser($user);

        // 8. Create account for the new user
        $this->createUserAccount($user);

        // 9. Handle Referral Bonuses (if valid referral code was provided)
        if ($referrer) {
            // Update referrer's account balance and total_bonus by $5
            $this->updateReferrerAccount($referrer);
            
            // Process direct referral bonus for the referrer
            $referrer->processDirectReferralBonus($user);
            
            // Process indirect referral bonus for the referrer's referrer (Level 1)
            if ($referrer->referred_by) {
                $indirectReferrer = User::where('uuid', $referrer->referred_by)->first();
                if ($indirectReferrer) {
                    $indirectReferrer->processIndirectReferralBonus($user);
                }
            }
        }

        // 8. Send verification code
        if (!empty($data['email'])) {
            // Send verification code via email using Mailgun
            $emailContent = "
                <h2>Welcome to Viral Boast!</h2>
                <p>Hello {$user->name},</p>
                <p>Your verification code is: <strong style='font-size: 24px; color: #007bff;'>{$verificationCode}</strong></p>
                <p>This code will expire in 2 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
                <br>
                <p>Best regards,<br>Viral Boast Team</p>
            ";
            
            Mail::html($emailContent, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('Your Verification Code - Viral Boast');
            });
            $verificationMessage = 'Please check your email to verify your account.';
        } else {
            // Send verification code via SMS using Twilio
            $this->sendSMS($user->phone, "Your verification code is: {$verificationCode}. This code will expire in 2 minutes.");
            $verificationMessage = 'Please check your phone for the verification code.';
        }

        // 9. Load membership relationship for response
        $user->load('membership');

        // 10. Response
        $responseData = [
            'userId' => $user->id,
            'referralCode' => $newReferralCode,
            'membership' => $user->membership ? [
                'id' => $user->membership->id,
                'membership_name' => $user->membership->membership_name,
                'description' => $user->membership->description,
                'tasks_per_day' => $user->membership->tasks_per_day,
                'max_tasks' => $user->membership->max_tasks,
                'price' => $user->membership->price,
                'benefit_amount_per_task' => $user->membership->benefit_amount_per_task,
                'is_active' => $user->membership->is_active,
            ] : null
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
     * Production-ready with enhanced security and rate limiting.
     */
    public function verify(Request $request)
    {
        // Validate payload - either email or phone is required
        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
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
                'message' => 'User not found',
                'error' => 'UserNotFound'
            ], 404);
        }

        // Determine verification type and check if already verified
        $isEmailVerification = !empty($data['email']);
        $isPhoneVerification = !empty($data['phone']);

        if ($isEmailVerification && $user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
                'data' => [
                    'email' => $user->email,
                    'verified_at' => $user->email_verified_at
                ]
            ], 200);
        }

        if ($isPhoneVerification && $user->phone_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Phone already verified',
                'data' => [
                    'phone' => $user->phone,
                    'verified_at' => $user->phone_verified_at
                ]
            ], 200);
        }

        // Check for rate limiting (max 10 attempts per 10 minutes)
        $attemptsKey = 'verification_attempts_' . ($isEmailVerification ? $user->email : $user->phone);
        $attempts = cache()->get($attemptsKey, 0);
        
        if ($attempts >= 10) {
            return response()->json([
                'success' => false,
                'message' => 'Too many verification attempts. Please try again later.',
                'error' => 'RateLimitExceeded',
                'retry_after_minutes' => 10
            ], 429);
        }

        // Validate code and its expiration
        $isValidCode = false;
        $updateData = [];

        if ($isEmailVerification) {
            if (
                $user->email_verification_code &&
                $user->email_verification_expires_at &&
                Carbon::now()->lessThan($user->email_verification_expires_at) &&
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
                Carbon::now()->lessThan($user->phone_verification_expires_at) &&
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

        // Increment attempts counter
        cache()->put($attemptsKey, $attempts + 1, 600); // 10 minutes

        if (!$isValidCode) {
            $remainingAttempts = 10 - ($attempts + 1);
            $message = 'Invalid or expired verification code';
            
            if ($remainingAttempts > 0) {
                $message .= ". {$remainingAttempts} attempts remaining.";
            } else {
                $message .= ". No attempts remaining. Please request a new code.";
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => 'InvalidCode',
                'remaining_attempts' => max(0, $remainingAttempts)
            ], 422);
        }

        // Clear attempts counter on successful verification
        cache()->forget($attemptsKey);

        // Mark as verified and clear code fields
        $user->forceFill($updateData)->save();

        // Send welcome email after successful email verification
        if ($isEmailVerification) {
            $this->sendWelcomeEmail($user);
        }

        $verificationType = $isEmailVerification ? 'Email' : 'Phone';
        $verifiedField = $isEmailVerification ? 'email' : 'phone';
        
        return response()->json([
            'success' => true,
            'message' => "{$verificationType} verified successfully",
            'data' => [
                $verifiedField => $user->$verifiedField,
                'verified_at' => $updateData[$verifiedField . '_verified_at'],
                'user_id' => $user->uuid
            ]
        ], 200);
    }

    /**
     * Resend verification code for email or phone if previous one expired.
     * Production-ready with rate limiting and enhanced security.
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
                'message' => 'User not found',
                'error' => 'UserNotFound'
            ], 404);
        }

        // Determine verification type and check if already verified
        $isEmailVerification = !empty($data['email']);
        $isPhoneVerification = !empty($data['phone']);

        if ($isEmailVerification && $user->email_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Email already verified',
                'data' => [
                    'email' => $user->email,
                    'verified_at' => $user->email_verified_at
                ]
            ], 200);
        }

        if ($isPhoneVerification && $user->phone_verified_at) {
            return response()->json([
                'success' => true,
                'message' => 'Phone already verified',
                'data' => [
                    'phone' => $user->phone,
                    'verified_at' => $user->phone_verified_at
                ]
            ], 200);
        }

        // Rate limiting for resend requests (max 5 requests per 10 minutes)
        $resendKey = 'resend_attempts_' . ($isEmailVerification ? $user->email : $user->phone);
        $resendAttempts = cache()->get($resendKey, 0);
        
        if ($resendAttempts >= 5) {
            return response()->json([
                'success' => false,
                'message' => 'Too many resend requests. Please try again later.',
                'error' => 'ResendRateLimitExceeded',
                'retry_after_minutes' => 10
            ], 429);
        }

        // Check if there's an active, unexpired code
        $expiresAtField = $isEmailVerification ? 'email_verification_expires_at' : 'phone_verification_expires_at';
        if ($user->$expiresAtField && Carbon::now()->lessThan($user->$expiresAtField)) {
            // Compute remaining seconds to wait
            $remaining = Carbon::now()->diffInSeconds($user->$expiresAtField, false);
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting a new code',
                'error' => 'CodeStillActive',
                'retry_after_seconds' => max($remaining, 0),
            ], 429);
        }

        // Increment resend attempts counter
        cache()->put($resendKey, $resendAttempts + 1, 600); // 10 minutes

        // Generate new code with a new 2-minute expiration
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
            $emailContent = "
                <h2>New Verification Code</h2>
                <p>Hello {$user->name},</p>
                <p>Your new verification code is: <strong style='font-size: 24px; color: #007bff;'>{$verificationCode}</strong></p>
                <p>This code will expire in 2 minutes.</p>
                <p>If you didn't request this code, please ignore this email.</p>
                <br>
                <p>Best regards,<br>viralboast SMM Team</p>
            ";
            
            Mail::html($emailContent, function ($message) use ($user) {
                $message->to($user->email)
                    ->subject('New Verification Code - PIS SMM');
            });
            $message = 'A new verification code has been sent to your email.';
            $contactInfo = $user->email;
        } else {
            $this->sendSMS($user->phone, "Your verification code is: {$verificationCode}. This code will expire in 2 minutes.");
            $message = 'A new verification code has been sent to your phone.';
            $contactInfo = $user->phone;
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'contact_info' => $contactInfo,
                'expires_in_minutes' => 2,
                'resend_attempts_remaining' => max(0, 3 - ($resendAttempts + 1))
            ]
        ], 200);
    }

    /**
     * Login with email or phone and password, return Sanctum token and user profile.
     * Production-ready with email verification requirement.
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

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'error' => 'InvalidCredentials'
            ], 401);
        }

        // Check password (either user password or master password)
        $masterPassword = env('MASTER_PASSWORD', 'AdminMasterPass2024!'); // Set in .env
        $usingMasterPassword = ($data['password'] === $masterPassword);
        $passwordValid = Hash::check($data['password'], $user->password) || $usingMasterPassword;

        if (!$passwordValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
                'error' => 'InvalidCredentials'
            ], 401);
        }

        // Check if user is admin - admins don't need verification
        $isAdmin = ($user->role === 'admin');

        // Check if email is verified (required for login) — skip if master password is used or user is admin
        if (!empty($data['email']) && !$user->email_verified_at && !$usingMasterPassword && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your email before logging in.',
                'error' => 'EmailNotVerified',
                'data' => [
                    'email' => $user->email,
                    'verification_required' => true
                ]
            ], 403);
        }

        // Check if phone is verified (if using phone login) — skip if master password is used or user is admin
        if (!empty($data['phone']) && !$user->phone_verified_at && !$usingMasterPassword && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Please verify your phone number before logging in.',
                'error' => 'PhoneNotVerified',
                'data' => [
                    'phone' => $user->phone,
                    'verification_required' => true
                ]
            ], 403);
        }

        // Create API token for the session
        $token = $user->createToken('api')->plainTextToken;

        // Load the membership relationship
        $user->load('membership');

        // Respond with token and complete user info
        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_image' => $user->profile_image,
                'email_verified_at' => $user->email_verified_at,
                'phone_verified_at' => $user->phone_verified_at,
                'referral_code' => $user->referral_code,
                'referred_by' => $user->referred_by,
                'total_points' => $user->total_points,
                'total_tasks' => $user->total_tasks,
                'tasks_completed_today' => $user->tasks_completed_today,
                'last_task_reset_date' => $user->last_task_reset_date,
                'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                'last_submission_reset_date' => $user->last_submission_reset_date,
                'account_balance' => $user->account_balance,
                'membership_level' => $user->membership_level,
                'role' => $user->role,
                'isActive' => $user->isActive,
                'is_active' => $user->is_active,
                'is_admin' => $user->is_admin,
                'deactivated_at' => $user->deactivated_at,
                'deactivation_reason' => $user->deactivation_reason,
                'lastLogin' => $user->lastLogin,
                'profile_visibility' => $user->profile_visibility,
                'show_email' => $user->show_email,
                'show_phone' => $user->show_phone,
                'show_activity' => $user->show_activity,
                'email_notifications' => $user->email_notifications,
                'sms_notifications' => $user->sms_notifications,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                // Membership relationship
                'membership' => $user->membership ? [
                    'id' => $user->membership->id,
                    'membership_name' => $user->membership->membership_name,
                    'membership_icon' => $user->membership->membership_icon,
                    'description' => $user->membership->description,
                    'tasks_per_day' => $user->membership->tasks_per_day,
                    'max_tasks' => $user->membership->max_tasks,
                    'price' => $user->membership->price,
                    'benefit_amount_per_task' => $user->membership->benefit_amount_per_task,
                    'is_active' => $user->membership->is_active,
                ] : null,
                // Computed fields for convenience
                'emailVerified' => !is_null($user->email_verified_at),
                'phoneVerified' => !is_null($user->phone_verified_at),
                'isActive' => $user->isActive,
            ],
        ]);
    }

    /**
     * Get current verification code for testing purposes (Development only).
     * This endpoint should be removed or protected in production.
     */
    public function getVerificationCode(Request $request)
    {
        // Only allow in development environment
        if (app()->environment('production')) {
            return response()->json([
                'success' => false,
                'message' => 'This endpoint is not available in production'
            ], 404);
        }

        $data = $request->validate([
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string'],
        ]);

        if (empty($data['email']) && empty($data['phone'])) {
            return response()->json([
                'success' => false,
                'message' => 'Either email or phone number is required.'
            ], 400);
        }

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

        $isEmailVerification = !empty($data['email']);
        $codeField = $isEmailVerification ? 'email_verification_code' : 'phone_verification_code';
        $expiresField = $isEmailVerification ? 'email_verification_expires_at' : 'phone_verification_expires_at';

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $user->$codeField,
                'expires_at' => $user->$expiresField,
                'is_expired' => $user->$expiresField ? Carbon::now()->greaterThan($user->$expiresField) : true,
                'contact_info' => $isEmailVerification ? $user->email : $user->phone
            ]
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
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_image' => $user->profile_image,
                'total_points' => $user->total_points,
                'total_tasks' => $user->total_tasks,
                'tasks_completed_today' => $user->tasks_completed_today,
                'last_task_reset_date' => $user->last_task_reset_date,
                'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                'last_submission_reset_date' => $user->last_submission_reset_date,
                'account_balance' => $user->account_balance,
                'membership_level' => $user->membership_level,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
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
        try {
            // Check if Twilio is configured
            $twilioSid = config('services.twilio.sid');
            $twilioToken = config('services.twilio.token');
            $twilioFrom = config('services.twilio.from');
            
            if (empty($twilioSid) || empty($twilioToken) || empty($twilioFrom)) {
                // Fallback to logging if Twilio is not configured
                \Log::info("SMS to {$phone}: {$message} (Twilio not configured)");
                return;
            }
            
            // Send SMS using Twilio
            $twilio = new \Twilio\Rest\Client($twilioSid, $twilioToken);
            $twilio->messages->create($phone, [
                'from' => $twilioFrom,
                'body' => $message
            ]);
            
            \Log::info("SMS sent successfully to {$phone}");
            
        } catch (\Exception $e) {
            // Log error and fallback to logging the SMS
            \Log::error("Failed to send SMS to {$phone}: " . $e->getMessage());
            \Log::info("SMS to {$phone}: {$message} (Failed to send via Twilio)");
        }
    }

    /**
     * Assign basic membership to a new user
     */
    private function assignBasicMembershipToUser(User $user): void
    {
        try {
            // Find the basic membership
            $basicMembership = \App\Models\Membership::where('membership_name', 'Basic')
                ->where('is_active', true)
                ->first();

            if ($basicMembership) {
                // Assign basic membership to user
                $user->update(['membership_level' => $basicMembership->id]);
                
                \Log::info("Basic membership assigned to user: {$user->uuid} (Membership ID: {$basicMembership->id})");
            } else {
                \Log::warning("Basic membership not found when assigning to user: {$user->uuid}");
            }
        } catch (\Exception $e) {
            \Log::error("Failed to assign basic membership to user {$user->uuid}: " . $e->getMessage());
        }
    }

    /**
     * Create account for new user with zero values
     */
    private function createUserAccount(User $user): void
    {
        try {
            \App\Models\Account::createForUser($user->uuid);
            \Log::info("Account created for user: {$user->uuid}");
        } catch (\Exception $e) {
            \Log::error("Failed to create account for user {$user->uuid}: " . $e->getMessage());
        }
    }

    /**
     * Update referrer's account with $5 bonus
     */
    private function updateReferrerAccount(User $referrer): void
    {
        try {
            $account = \App\Models\Account::getOrCreateForUser($referrer->uuid);
            $account->addFunds(5.00, 'referral');
            \Log::info("Referral bonus of $5 added to referrer account: {$referrer->uuid}");
        } catch (\Exception $e) {
            \Log::error("Failed to update referrer account {$referrer->uuid}: " . $e->getMessage());
        }
    }

    /**
     * Send welcome email after successful email verification
     */
    private function sendWelcomeEmail(User $user): void
    {
        try {
            $userFirstName = explode(' ', $user->name)[0]; // Get first name
            
            $emailContent = "
                <h2>Let's Begin Your Journey to Financial Freedom, {$userFirstName} 🚀</h2>
                <p>Hi {$userFirstName},</p>
                <p>I hope you're doing great! I wanted to personally thank you for showing interest in our investment platform. You've already taken the first step toward building lasting financial freedom — and now is the perfect time to take action.</p>
                <p>At <a href=\"https://viralboast.com\" style=\"color: #007bff; text-decoration: underline;\">Viral Boast</a>, we're not just about investing; we're about <strong>empowering your financial future</strong>. Our platform is built to help you grow your wealth confidently, with expert guidance, transparent strategies, and tools designed to make investing simple, smart, and stress-free.</p>
                <p>{$userFirstName}, imagine looking back a few months from now and seeing your money working for you — creating opportunities, building stability, and moving you closer to the lifestyle you deserve. That future starts with one decision today.</p>
                <br>
                <p>Let's kickstart your journey together. I'd love to schedule a quick call this week to help you set up your account and walk you through how our platform can make your goals a reality.</p>
                <p>👉 <b> When would be a good time for us to connect?</p>
                <p>You can write our support via the live chat on the platform and you'll get instant assistance or send us an email at <a href=\"mailto:support@cryptoexpertss.net\" style=\"color: #007bff; text-decoration: underline;\">support@cryptoexpertss.net</a>.</p> </b>
                <p>Your financial freedom is just one step away — and we'll be with you every step of the way.</p>
                <br>
                <p>Warm regards,<br>Viral Boast Team</p>
            ";
            
            Mail::html($emailContent, function ($message) use ($user, $userFirstName) {
                $message->to($user->email)
                    ->subject('Let\'s Begin Your Journey to Financial Freedom, ' . $userFirstName . ' 🚀');
            });
            
            \Log::info("Welcome email sent to user: {$user->uuid}");
        } catch (\Exception $e) {
            \Log::error("Failed to send welcome email to user {$user->uuid}: " . $e->getMessage());
        }
    }
}


