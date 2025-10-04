<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    /**
     * Get user's account information
     */
    public function getAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        return response()->json([
            'success' => true,
            'data' => $account->getAccountSummary()
        ]);
    }

    /**
     * Get account financial statistics
     */
    public function getFinancialStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        return response()->json([
            'success' => true,
            'data' => $account->getFinancialStats()
        ]);
    }

    /**
     * Update account fields
     */
    public function updateAccount(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'balance' => 'sometimes|numeric|min:0',
            'total_bonus' => 'sometimes|numeric|min:0',
            'total_withdrawals' => 'sometimes|numeric|min:0',
            'tasks_income' => 'sometimes|numeric|min:0',
            'referral_income' => 'sometimes|numeric|min:0',
            'total_earned' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        // Get only the fields that were provided in the request
        $updateData = $request->only([
            'balance',
            'total_bonus', 
            'total_withdrawals',
            'tasks_income',
            'referral_income',
            'total_earned',
            'is_active'
        ]);

        // Update last_activity_at when any field is updated
        $updateData['last_activity_at'] = now();

        if ($account->update($updateData)) {
            return response()->json([
                'success' => true,
                'message' => 'Account updated successfully',
                'data' => $account->fresh()->getAccountSummary()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to update account'
        ], 400);
    }

    /**
     * Add funds to account
     */
    public function addFunds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|string|in:bonus,referral,task,general,transfer',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        $amount = $request->input('amount');
        $type = $request->input('type');
        $description = $request->input('description', '');

        if ($account->addFunds($amount, $type)) {
            return response()->json([
                'success' => true,
                'message' => 'Funds added successfully',
                'data' => [
                    'amount_added' => $amount,
                    'type' => $type,
                    'new_balance' => $account->fresh()->balance,
                    'description' => $description
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to add funds'
        ], 400);
    }

    /**
     * Deduct funds from account
     */
    public function deductFunds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'required|string|in:withdrawal,transfer,penalty,other',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        $amount = $request->input('amount');
        $reason = $request->input('reason');
        $description = $request->input('description', '');

        if (!$account->hasSufficientBalance($amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
                'data' => [
                    'requested_amount' => $amount,
                    'available_balance' => $account->balance
                ]
            ], 400);
        }

        if ($account->deductFunds($amount, $reason)) {
            return response()->json([
                'success' => true,
                'message' => 'Funds deducted successfully',
                'data' => [
                    'amount_deducted' => $amount,
                    'reason' => $reason,
                    'new_balance' => $account->fresh()->balance,
                    'description' => $description
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to deduct funds'
        ], 400);
    }

    /**
     * Transfer funds to another user
     */
    public function transferFunds(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01',
            'recipient_uuid' => 'required|string|exists:users,uuid',
            'description' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        $amount = $request->input('amount');
        $recipientUuid = $request->input('recipient_uuid');
        $description = $request->input('description', '');

        // Check if trying to transfer to self
        if ($user->uuid === $recipientUuid) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot transfer funds to yourself'
            ], 400);
        }

        // Check sufficient balance
        if (!$account->hasSufficientBalance($amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
                'data' => [
                    'requested_amount' => $amount,
                    'available_balance' => $account->balance
                ]
            ], 400);
        }

        // Get or create recipient account
        $recipientAccount = Account::getOrCreateForUser($recipientUuid);

        if ($account->transferTo($recipientAccount, $amount, $description)) {
            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'data' => [
                    'amount_transferred' => $amount,
                    'recipient_uuid' => $recipientUuid,
                    'new_balance' => $account->fresh()->balance,
                    'description' => $description
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Transfer failed'
        ], 400);
    }

    /**
     * Get account balance
     */
    public function getBalance(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $account->balance,
                'available_balance' => $account->getAvailableBalance(),
                'currency' => 'USD'
            ]
        ]);
    }

    /**
     * Get account transaction history (placeholder)
     */
    public function getTransactionHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        // This would typically query a transactions table
        // For now, return account summary
        return response()->json([
            'success' => true,
            'message' => 'Transaction history feature coming soon',
            'data' => [
                'account_summary' => $account->getAccountSummary(),
                'note' => 'Transaction history will be implemented with a separate transactions table'
            ]
        ]);
    }

    /**
     * Deactivate account
     */
    public function deactivateAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        if ($account->deactivate()) {
            return response()->json([
                'success' => true,
                'message' => 'Account deactivated successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to deactivate account'
        ], 400);
    }

    /**
     * Activate account
     */
    public function activateAccount(Request $request): JsonResponse
    {
        $user = $request->user();
        $account = Account::getOrCreateForUser($user->uuid);

        if ($account->activate()) {
            return response()->json([
                'success' => true,
                'message' => 'Account activated successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to activate account'
        ], 400);
    }
}