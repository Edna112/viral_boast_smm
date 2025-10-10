<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WebAuthController extends Controller
{
    /**
     * Handle user registration with email verification (JSON response).
     */
    public function register(Request $request)
    {
        // Validate incoming registration data
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Generate email verification code expired in 2 minutes
        $verificationCode = random_int(100000, 999999);

        // Create user with hashed password and pending verification state
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'email_verification_code' => (string) $verificationCode,
            'email_verification_expires_at' => Carbon::now()->addMinutes(2),
        ]);

        // Send verification code via email
        Mail::raw("Your verification code is: {$verificationCode}", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Your Verification Code');
        });

        return response()->json([
            'message' => 'Registration successful. Verification code sent to email.',
            'email' => $user->email,
        ], 201);
    }

    /**
     * Handle email verification code submission (JSON response).
     */
    public function verify(Request $request)
    {
        // Validate email and code payload
        $data = $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string'],
        ]);

        // Look up the user by email
        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Short-circuit if already verified
        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified'], 200);
        }

        // Validate code and its expiration
        if (
            !$user->email_verification_code ||
            !$user->email_verification_expires_at ||
            Carbon::now()->greaterThan($user->email_verification_expires_at) ||
            $user->email_verification_code !== $data['code']
        ) {
            return response()->json(['message' => 'Invalid or expired verification code'], 422);
        }

        // Mark as verified and clear code fields
        $user->forceFill([
            'email_verified_at' => Carbon::now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return response()->json(['message' => 'Email verified successfully']);
    }

    /**
     * Resend email verification code if previous one expired (JSON response).
     */
    public function resendVerification(Request $request)
    {
        // Validate email
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // If already verified, no need to resend
        if ($user->email_verified_at) {
            return response()->json(['message' => 'Email already verified'], 200);
        }

        // Only allow resend when there is no active, unexpired code
        if ($user->email_verification_expires_at && Carbon::now()->lessThan($user->email_verification_expires_at)) {
            $remaining = Carbon::now()->diffInSeconds($user->email_verification_expires_at, false);
            return response()->json([
                'message' => 'Please wait before requesting a new code',
                'retry_after_seconds' => max($remaining, 0),
            ], 429);
        }

        // Generate new code with a new 2-minute expiration
        $verificationCode = random_int(100000, 999999);
        $user->forceFill([
            'email_verification_code' => (string) $verificationCode,
            'email_verification_expires_at' => Carbon::now()->addMinutes(2),
        ])->save();

        // Send the new verification code via email
        Mail::raw("Your verification code is: {$verificationCode}", function ($message) use ($user) {
            $message->to($user->email)
                ->subject('Your Verification Code');
        });

        return response()->json(['message' => 'A new verification code has been sent']);
    }

    /**
     * Handle user login with session authentication (JSON response).
     */
    public function login(Request $request)
    {
        // Validate login payload
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Attempt to authenticate user
        if (Auth::attempt($data, $request->boolean('remember'))) {
            $request->session()->regenerate();
            
            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => Auth::user()->id,
                    'uuid' => Auth::user()->uuid,
                    'name' => Auth::user()->name,
                    'email' => Auth::user()->email,
                    'phone' => Auth::user()->phone,
                    'total_points' => Auth::user()->total_points,
                    'tasks_completed_today' => Auth::user()->tasks_completed_today,
                    'last_task_reset_date' => Auth::user()->last_task_reset_date,
                    'tasks_submitted_today' => Auth::user()->getDailySubmissionsCount(),
                    'last_submission_reset_date' => Auth::user()->last_submission_reset_date,
                ],
            ]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    /**
     * Handle phone-based registration (JSON response).
     */
    public function registerPhone(Request $request)
    {
        // Validate required fields for phone-based registration
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:32', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Create user with phone as primary identifier
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'password' => Hash::make($data['password']),
        ]);

        // Auto-login the user after phone registration
        Auth::login($user);

        return response()->json([
            'message' => 'Registration successful! Welcome to Viral Boast.',
            'user' => [
                'id' => $user->id,
                'uuid' => $user->uuid,
                'name' => $user->name,
                'phone' => $user->phone,
                'total_points' => $user->total_points,
                'tasks_completed_today' => $user->tasks_completed_today,
                'last_task_reset_date' => $user->last_task_reset_date,
                'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                'last_submission_reset_date' => $user->last_submission_reset_date,
            ],
        ], 201);
    }

    /**
     * Handle phone-based login (JSON response).
     */
    public function loginPhone(Request $request)
    {
        // Validate login payload
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Attempt to authenticate user by phone
        $user = User::where('phone', $data['phone'])->first();
        
        if ($user && Hash::check($data['password'], $user->password)) {
            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();
            
            return response()->json([
                'message' => 'Login successful',
                'user' => [
                    'id' => $user->id,
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'total_points' => $user->total_points,
                    'tasks_completed_today' => $user->tasks_completed_today,
                    'last_task_reset_date' => $user->last_task_reset_date,
                    'tasks_submitted_today' => $user->getDailySubmissionsCount(),
                    'last_submission_reset_date' => $user->last_submission_reset_date,
                ],
            ]);
        }

        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    /**
     * Handle forgot password request (JSON response).
     */
    public function forgotPassword(Request $request)
    {
        // Validate email input
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        // Use Laravel's Password Broker to send reset link
        $status = app('auth.password.broker')->sendResetLink(['email' => $data['email']]);

        if ($status === \Illuminate\Support\Facades\Password::RESET_LINK_SENT) {
            return response()->json(['message' => 'Password reset link sent to your email']);
        }

        return response()->json(['message' => 'Unable to send reset link'], 422);
    }

    /**
     * Handle password reset (JSON response).
     */
    public function resetPassword(Request $request)
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
            return response()->json(['message' => 'Password reset successful']);
        }

        return response()->json(['message' => 'Invalid token or email'], 422);
    }

    /**
     * Handle user logout (JSON response).
     */
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get user dashboard/profile (JSON response).
     */
    public function dashboard()
    {
        return response()->json([
            'user' => Auth::user(),
            'message' => 'Welcome to your dashboard'
        ]);
    }
}
