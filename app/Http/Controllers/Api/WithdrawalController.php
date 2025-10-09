<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    /**
     * Create a withdrawal request
     */
    public function createWithdrawal(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'withdrawal_amount' => 'required|numeric|min:1',
                'platform' => 'nullable|string|max:100',
                'picture_path' => 'nullable|string|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $withdrawalAmount = $request->input('withdrawal_amount');

            // Check if user has sufficient balance
            $account = Account::getOrCreateForUser($user->uuid);
            
            if ($account->balance < $withdrawalAmount) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient balance. Your current balance is $' . number_format($account->balance, 2),
                    'current_balance' => $account->balance,
                    'requested_amount' => $withdrawalAmount
                ], 400);
            }

            // Create withdrawal request
            $withdrawal = Withdrawal::create([
                'user_uuid' => $user->uuid,
                'withdrawal_amount' => $withdrawalAmount,
                'platform' => $request->input('platform'),
                'picture_path' => $request->input('picture_path'),
                'is_completed' => false
            ]);

            $withdrawal->load('user:uuid,name,email');

            return response()->json([
                'success' => true,
                'data' => $withdrawal,
                'message' => 'Withdrawal request created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get withdrawal request by UUID
     */
    public function getWithdrawal(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $withdrawal = Withdrawal::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->with('user:uuid,name,email')
                ->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $withdrawal,
                'message' => 'Withdrawal request retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete withdrawal request
     */
    public function deleteWithdrawal(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $withdrawal = Withdrawal::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request not found'
                ], 404);
            }

            // Only allow deletion if not completed
            if ($withdrawal->is_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete completed withdrawal request'
                ], 403);
            }

            $withdrawal->delete();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's withdrawal requests
     */
    public function getUserWithdrawals(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = Withdrawal::where('user_uuid', $user->uuid)
                ->with('user:uuid,name,email');

            // Filter by completion status
            if ($request->has('is_completed')) {
                $query->where('is_completed', $request->boolean('is_completed'));
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->input('platform'));
            }

            $withdrawals = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $withdrawals,
                'message' => 'Withdrawal requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving withdrawal requests: ' . $e->getMessage()
            ], 500);
        }
    }
}
