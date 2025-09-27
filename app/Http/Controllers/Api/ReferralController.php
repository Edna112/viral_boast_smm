<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ReferralController extends Controller
{
    /**
     * Validate a referral code
     */
    public function validateReferralCode(Request $request): JsonResponse
    {
        $request->validate([
            'referral_code' => 'required|string|max:20'
        ]);

        $referralCode = $request->input('referral_code');
        $validation = User::validateReferralCodeWithLimits($referralCode);

        if ($validation['valid']) {
            return response()->json([
                'success' => true,
                'message' => $validation['message'],
                'data' => $validation['referrer']
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $validation['message'],
            'error' => $validation['error']
        ], 400);
    }

    /**
     * Get referral statistics for authenticated user
     */
    public function getReferralStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $stats = $user->getComprehensiveReferralStats();

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Check if user can use a referral code
     */
    public function canUseReferralCode(Request $request): JsonResponse
    {
        $request->validate([
            'referral_code' => 'required|string|max:20'
        ]);

        $user = $request->user();
        $referralCode = $request->input('referral_code');

        $canUse = $user->canUseReferralCode($referralCode);

        if ($canUse) {
            $referrer = User::getByReferralCode($referralCode);
            return response()->json([
                'success' => true,
                'message' => 'You can use this referral code',
                'data' => [
                    'can_use' => true,
                    'referrer_name' => $referrer->name,
                    'referrer_code' => $referrer->referral_code
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'You cannot use this referral code',
            'data' => [
                'can_use' => false,
                'reason' => $user->referral_code === $referralCode ? 'self_referral' : 'invalid_code'
            ]
        ], 400);
    }

    /**
     * Get user by referral code (public endpoint)
     */
    public function getUserByReferralCode(Request $request): JsonResponse
    {
        $request->validate([
            'referral_code' => 'required|string|max:20'
        ]);

        $referralCode = $request->input('referral_code');
        $user = User::getByReferralCode($referralCode);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found with this referral code'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'referral_code' => $user->referral_code,
                'total_referrals' => $user->referrals()->count(),
                'referral_url' => url('/register?ref=' . $user->referral_code)
            ]
        ]);
    }
}
