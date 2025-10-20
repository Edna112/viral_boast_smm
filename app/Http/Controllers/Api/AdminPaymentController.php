<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Services\EmailNotificationService;

class AdminPaymentController extends Controller
{
    /**
     * Get all payments (Admin only)
     */
    public function getAllPayments(Request $request): JsonResponse
    {
        try {
            $query = Payment::with('user:uuid,name,email');

            // Filter by approval status
            if ($request->has('is_approved')) {
                $query->where('is_approved', $request->boolean('is_approved'));
            }

            // Filter by minimum amount
            if ($request->has('min_amount')) {
                $query->where('amount', '>=', $request->input('min_amount'));
            }

            // Filter by conversion currency
            if ($request->has('conversion_currency')) {
                $query->where('conversion_currency', $request->input('conversion_currency'));
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->input('platform'));
            }

            // Filter by user UUID
            if ($request->has('user_uuid')) {
                $query->where('user_uuid', $request->input('user_uuid'));
            }

            // Search by description
            if ($request->has('search')) {
                $query->where('description', 'like', '%' . $request->input('search') . '%');
            }

            $payments = $query->orderBy('created_at', 'desc')->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $payments,
                'message' => 'All payments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment by UUID (Admin only)
     */
    public function getPaymentById(string $uuid): JsonResponse
    {
        try {
            $payment = Payment::where('uuid', $uuid)
                ->with('user:uuid,name,email')
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Payment retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve any payment (Admin only)
     */
    public function approvePayment(string $uuid): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Find the payment
            $payment = Payment::where('uuid', $uuid)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Check if payment is already approved
            if ($payment->is_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is already approved'
                ], 400);
            }

            // Approve the payment
            $payment->update(['is_approved' => true]);

            // Get or create user's account
            $account = Account::getOrCreateForUser($payment->user_uuid);

            // Add the payment amount to user's account balance
            $account->addFunds($payment->amount, 'payment');

            DB::commit();

            $payment->load('user:uuid,name,email');

            // Send payment approved email notification
            $emailService = new EmailNotificationService();
            $emailService->sendPaymentApprovedNotification($payment->user, [
                'amount' => $payment->amount,
                'currency' => $payment->conversion_currency ?? 'USD',
                'transaction_id' => $payment->uuid,
                'balance' => $account->balance
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => $payment,
                    'account' => $account->getAccountSummary()
                ],
                'message' => 'Payment approved successfully and funds added to account'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error approving payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete any payment (Admin only)
     */
    public function deletePayment(string $uuid): JsonResponse
    {
        try {
            $payment = Payment::where('uuid', $uuid)->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Store payment info before deletion for response
            $paymentInfo = [
                'uuid' => $payment->uuid,
                'amount' => $payment->amount,
                'user_uuid' => $payment->user_uuid,
                'is_approved' => $payment->is_approved,
                'created_at' => $payment->created_at
            ];

            $payment->delete();

            return response()->json([
                'success' => true,
                'data' => [
                    'deleted_payment' => $paymentInfo
                ],
                'message' => 'Payment deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting payment: ' . $e->getMessage()
            ], 500);
        }
    }
}
