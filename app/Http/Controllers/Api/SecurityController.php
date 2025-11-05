<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;

class SecurityController extends Controller
{
    /**
     * Generate QR code data for enabling 2FA
     */
    public function generate2FA(Request $request)
    {
        $user = $request->user();
        
        // Generate a random secret key (32 characters)
        $secret = $this->generateSecret();
        
        // Store secret temporarily (will be enabled after verification)
        $user->update([
            'two_factor_secret' => Crypt::encryptString($secret),
        ]);
        
        // Generate QR code URL for Google Authenticator
        $qrCodeUrl = $this->getQRCodeUrl(
            $user->name,
            $user->email,
            $secret
        );
        
        return response()->json([
            'success' => true,
            'message' => '2FA secret generated. Scan QR code with your authenticator app.',
            'data' => [
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl,
                'manual_entry_key' => $this->formatSecretKey($secret),
            ]
        ]);
    }
    
    /**
     * Enable 2FA after verifying the code
     */
    public function enable2FA(Request $request)
    {
        // Accept both 'code' and 'token' field names for frontend compatibility
        $request->validate([
            'code' => ['sometimes', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
            'token' => ['sometimes', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);
        
        // Get code from either 'code' or 'token' field
        $code = $request->input('code') ?? $request->input('token');
        
        if (!$code) {
            return response()->json([
                'success' => false,
                'message' => 'Verification code is required.',
                'error' => 'CodeRequired'
            ], 400);
        }
        
        $user = $request->user();
        
        // Check if secret exists
        if (!$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => 'Please generate 2FA secret first.',
                'error' => 'SecretNotFound'
            ], 400);
        }
        
        // Get the secret
        $secret = Crypt::decryptString($user->two_factor_secret);
        
        // Verify the code
        if (!$this->verifyCode($secret, $code)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code. Please try again.',
                'error' => 'InvalidCode'
            ], 422);
        }
        
        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();
        
        // Enable 2FA
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '2FA enabled successfully.',
            'data' => [
                'recovery_codes' => $recoveryCodes,
                'two_factor_enabled' => true,
            ]
        ]);
    }
    
    /**
     * Disable 2FA
     */
    public function disable2FA(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);
        
        $user = $request->user();
        
        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
                'error' => 'InvalidPassword'
            ], 422);
        }
        
        // Disable 2FA
        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '2FA disabled successfully.',
        ]);
    }
    
    /**
     * Get 2FA status
     */
    public function get2FAStatus(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => [
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
                'two_factor_secret_set' => !is_null($user->two_factor_secret),
            ]
        ]);
    }
    
    /**
     * Verify 2FA code (used during login)
     */
    public function verify2FACode(Request $request)
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);
        
        $user = $request->user();
        
        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled for this account.',
                'error' => '2FANotEnabled'
            ], 400);
        }
        
        $secret = Crypt::decryptString($user->two_factor_secret);
        
        // Verify code
        if ($this->verifyCode($secret, $request->code)) {
            return response()->json([
                'success' => true,
                'message' => '2FA code verified successfully.',
            ]);
        }
        
        // Check recovery codes
        $recoveryCodes = json_decode($user->two_factor_recovery_codes ?? '[]', true);
        if (in_array($request->code, $recoveryCodes)) {
            // Remove used recovery code
            $recoveryCodes = array_values(array_diff($recoveryCodes, [$request->code]));
            $user->update([
                'two_factor_recovery_codes' => json_encode($recoveryCodes),
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Recovery code accepted.',
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Invalid 2FA code.',
            'error' => 'InvalidCode'
        ], 422);
    }
    
    /**
     * Generate recovery codes
     */
    public function generateNewRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);
        
        $user = $request->user();
        
        // Verify password
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid password.',
                'error' => 'InvalidPassword'
            ], 422);
        }
        
        if (!$user->two_factor_enabled) {
            return response()->json([
                'success' => false,
                'message' => '2FA is not enabled.',
                'error' => '2FANotEnabled'
            ], 400);
        }
        
        $recoveryCodes = $this->generateRecoveryCodes();
        
        $user->update([
            'two_factor_recovery_codes' => json_encode($recoveryCodes),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'New recovery codes generated.',
            'data' => [
                'recovery_codes' => $recoveryCodes,
            ]
        ]);
    }
    
    /**
     * Generate a random secret key
     */
    private function generateSecret($length = 32)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 characters
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $secret;
    }
    
    /**
     * Get QR code URL for Google Authenticator
     */
    private function getQRCodeUrl($name, $email, $secret)
    {
        $issuer = config('app.name', 'Viral Boast SMM');
        $label = $name . ' (' . $email . ')';
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label) . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer);
    }
    
    /**
     * Format secret key for manual entry (add spaces every 4 characters)
     */
    private function formatSecretKey($secret)
    {
        return chunk_split($secret, 4, ' ');
    }
    
    /**
     * Verify TOTP code
     */
    private function verifyCode($secret, $code, $window = 1)
    {
        $time = floor(time() / 30);
        
        // Check current time and previous/next windows
        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = $this->calculateTOTP($secret, $time + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Calculate TOTP code
     */
    private function calculateTOTP($secret, $time)
    {
        $secret = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $time);
        $hash = hash_hmac('sha1', $time, $secret, true);
        $offset = ord($hash[19]) & 0xf;
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Base32 decode
     */
    private function base32Decode($secret)
    {
        $base32chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $base32charsFlipped = array_flip(str_split($base32chars));
        
        $paddingCharCount = substr_count($secret, '=');
        $allowedValues = [6, 4, 3, 1, 0];
        if (!in_array($paddingCharCount, $allowedValues)) {
            return false;
        }
        
        for ($i = 0; $i < 4; $i++) {
            if ($paddingCharCount == $allowedValues[$i] && substr($secret, -($allowedValues[$i])) != str_repeat('=', $allowedValues[$i])) {
                return false;
            }
        }
        
        $secret = str_replace('=', '', $secret);
        $secret = str_split($secret);
        $binaryString = '';
        
        for ($i = 0; $i < count($secret); $i = $i + 8) {
            $x = '';
            if (!in_array($secret[$i], $base32chars)) {
                return false;
            }
            for ($j = 0; $j < 8; $j++) {
                if (isset($secret[$i + $j])) {
                    $x .= str_pad(base_convert($base32charsFlipped[$secret[$i + $j]], 10, 2), 5, '0', STR_PAD_LEFT);
                }
            }
            $eightBits = str_split($x, 8);
            for ($z = 0; $z < count($eightBits); $z++) {
                $binaryString .= (($y = chr(base_convert($eightBits[$z], 2, 10))) || ord($y) == 48) ? $y : '';
            }
        }
        
        return $binaryString;
    }
    
    /**
     * Generate recovery codes
     */
    private function generateRecoveryCodes($count = 8)
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(8) . '-' . Str::random(8));
        }
        return $codes;
    }
}
