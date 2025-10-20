<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use App\Services\EmailNotificationService;

class AdminWithdrawalController extends Controller
{
    /**
     * Get all withdrawal requests (Admin only)
     */
    public function getAllWithdrawals(Request $request): JsonResponse
    {
        try {
            $query = Withdrawal::with('user:uuid,name,email');

            // Filter by completion status
            if ($request->has('is_completed')) {
                $query->where('is_completed', $request->boolean('is_completed'));
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->input('platform'));
            }

            // Filter by user UUID
            if ($request->has('user_uuid')) {
                $query->where('user_uuid', $request->input('user_uuid'));
            }

            // Filter by minimum amount
            if ($request->has('min_amount')) {
                $query->where('withdrawal_amount', '>=', $request->input('min_amount'));
            }

            $withdrawals = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $withdrawals,
                'message' => 'All withdrawal requests retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving withdrawal requests: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get withdrawal request by UUID (Admin only)
     */
    public function getWithdrawalById(string $uuid): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::where('uuid', $uuid)
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
     * Complete withdrawal request (Admin only)
     */
    public function completeWithdrawal(string $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Find the withdrawal
            $withdrawal = Withdrawal::where('uuid', $uuid)->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request not found'
                ], 404);
            }

            // Check if withdrawal is already completed
            if ($withdrawal->is_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request is already completed'
                ], 400);
            }

            // Get user's account
            $account = Account::getOrCreateForUser($withdrawal->user_uuid);

            // Check if user still has sufficient balance
            if ($account->balance < $withdrawal->withdrawal_amount) {
                return response()->json([
                    'success' => false,
                    'message' => 'User has insufficient balance to complete withdrawal',
                    'user_balance' => $account->balance,
                    'withdrawal_amount' => $withdrawal->withdrawal_amount
                ], 400);
            }

            // Deduct amount from user's account
            $account->deductFunds($withdrawal->withdrawal_amount, 'withdrawal');

            // Mark withdrawal as completed
            $withdrawal->update(['is_completed' => true]);

            DB::commit();

            $withdrawal->load('user:uuid,name,email');

            // Send withdrawal approved email notification
            $emailService = new EmailNotificationService();
            $emailService->sendWithdrawalApprovedNotification($withdrawal->user, [
                'amount' => $withdrawal->withdrawal_amount,
                'currency' => 'USD',
                'transaction_id' => $withdrawal->uuid,
                'withdrawal_method' => $withdrawal->platform ?? 'Unknown',
                'account_details' => $withdrawal->account_details,
                'wallet_address' => $withdrawal->wallet_address,
                'address_type' => $withdrawal->address_type,
                'balance' => $account->balance
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'withdrawal' => $withdrawal,
                    'account' => $account->getAccountSummary()
                ],
                'message' => 'Withdrawal request completed successfully and amount deducted from account'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error completing withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject withdrawal request (Admin only)
     */
    public function rejectWithdrawal(Request $request, string $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the withdrawal
            $withdrawal = Withdrawal::where('uuid', $uuid)->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request not found'
                ], 404);
            }

            // Check if withdrawal is already completed
            if ($withdrawal->is_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request is already completed and cannot be rejected'
                ], 400);
            }

            // Get user's account for balance info
            $account = Account::getOrCreateForUser($withdrawal->user_uuid);

            // Store withdrawal info before deletion
            $withdrawalInfo = [
                'uuid' => $withdrawal->uuid,
                'withdrawal_amount' => $withdrawal->withdrawal_amount,
                'user_uuid' => $withdrawal->user_uuid,
                'platform' => $withdrawal->platform,
                'created_at' => $withdrawal->created_at
            ];

            // Load user info for email
            $withdrawal->load('user:uuid,name,email');

            // Send rejection email notification
            $emailService = new EmailNotificationService();
            $emailService->sendWithdrawalRejectedNotification($withdrawal->user, [
                'amount' => $withdrawal->withdrawal_amount,
                'currency' => 'USD',
                'transaction_id' => $withdrawal->uuid,
                'withdrawal_method' => $withdrawal->platform ?? 'Unknown',
                'account_details' => $withdrawal->account_details,
                'wallet_address' => $withdrawal->wallet_address,
                'address_type' => $withdrawal->address_type,
                'reason' => $request->input('reason'),
                'balance' => $account->balance
            ]);

            // Delete the withdrawal request
            $withdrawal->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => [
                    'rejected_withdrawal' => $withdrawalInfo,
                    'reason' => $request->input('reason'),
                    'account' => $account->getAccountSummary()
                ],
                'message' => 'Withdrawal request rejected successfully and user notified via email'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete withdrawal request (Admin only)
     */
    public function deleteWithdrawal(string $uuid): JsonResponse
    {
        try {
            $withdrawal = Withdrawal::where('uuid', $uuid)->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request not found'
                ], 404);
            }

            // Store withdrawal info before deletion for response
            $withdrawalInfo = [
                'uuid' => $withdrawal->uuid,
                'withdrawal_amount' => $withdrawal->withdrawal_amount,
                'user_uuid' => $withdrawal->user_uuid,
                'is_completed' => $withdrawal->is_completed,
                'created_at' => $withdrawal->created_at
            ];

            $withdrawal->delete();

            return response()->json([
                'success' => true,
                'data' => [
                    'deleted_withdrawal' => $withdrawalInfo
                ],
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
     * Edit withdrawal request (Admin only)
     */
    public function editWithdrawal(Request $request, string $uuid): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'withdrawal_amount' => 'sometimes|numeric|min:1',
                'platform' => 'nullable|string|max:100',
                'account_details' => 'nullable|string|max:500',
                'wallet_address' => 'nullable|string|max:500',
                'address_type' => 'nullable|string|max:100',
                'picture_path' => 'nullable|string|url',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $withdrawal = Withdrawal::where('uuid', $uuid)->first();

            if (!$withdrawal) {
                return response()->json([
                    'success' => false,
                    'message' => 'Withdrawal request not found'
                ], 404);
            }

            // Only allow editing if not completed
            if ($withdrawal->is_completed) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot edit completed withdrawal request'
                ], 403);
            }

            $data = $request->only(['withdrawal_amount', 'platform', 'account_details', 'wallet_address', 'address_type', 'picture_path']);
            $withdrawal->update($data);

            $withdrawal->load('user:uuid,name,email');

            return response()->json([
                'success' => true,
                'data' => $withdrawal,
                'message' => 'Withdrawal request updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }
}
