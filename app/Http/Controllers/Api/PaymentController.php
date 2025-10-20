<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\User;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Services\EmailNotificationService;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = Payment::where('user_uuid', $user->uuid)
                ->with('user:uuid,name,email');

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

            $payments = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'success' => true,
                'data' => $payments,
                'message' => 'Payments retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'amount' => 'required|numeric|min:10',
                'description' => 'nullable|string|max:1000',
                'picture_path' => 'required|string|url', // Required string URL
                'platform' => 'nullable|string|max:100', // Platform used for payment
                'conversion_amount' => 'nullable|numeric',
                'conversion_currency' => 'nullable|in:USD,bitcoin,ethereum,btc'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            $data = $request->only(['amount', 'description', 'picture_path', 'platform', 'conversion_amount', 'conversion_currency']);
            $data['user_uuid'] = $user->uuid;

            $payment = Payment::create($data);
            $payment->load('user:uuid,name,email');

            // Send payment received email notification
            $emailService = new EmailNotificationService();
            $emailService->sendPaymentNotification($payment->user, [
                'amount' => $payment->amount,
                'currency' => $payment->conversion_currency ?? 'USD',
                'transaction_id' => $payment->uuid,
                'payment_method' => $payment->platform ?? 'Unknown',
                'balance' => 0 // Will be updated when approved
            ]);

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Payment created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $payment = Payment::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $payment = Payment::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Only allow updates if payment is not approved
            if ($payment->is_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update approved payment'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'amount' => 'sometimes|numeric|min:10',
                'description' => 'nullable|string|max:1000',
                'picture_path' => 'sometimes|required|string|url',
                'platform' => 'nullable|string|max:100',
                'conversion_amount' => 'nullable|numeric',
                'conversion_currency' => 'nullable|in:USD,bitcoin,ethereum,btc'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $request->only(['amount', 'description', 'picture_path', 'platform', 'conversion_amount', 'conversion_currency']);

            $payment->update($data);

            $payment->load('user:uuid,name,email');

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Payment updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $payment = Payment::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Only allow deletion if payment is not approved
            if ($payment->is_approved) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete approved payment'
                ], 403);
            }

            $payment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payment deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting payment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate conversion for a payment.
     */
    public function calculateConversion(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $payment = Payment::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'currency' => 'required|in:bitcoin,ethereum,btc'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payment->calculateConversion($request->input('currency'));
            $payment->load('user:uuid,name,email');

            return response()->json([
                'success' => true,
                'data' => $payment,
                'message' => 'Conversion calculated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating conversion: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment statistics for the authenticated user.
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $stats = [
                'total_payments' => Payment::where('user_uuid', $user->uuid)->count(),
                'approved_payments' => Payment::where('user_uuid', $user->uuid)->approved()->count(),
                'pending_payments' => Payment::where('user_uuid', $user->uuid)->pending()->count(),
                'total_amount' => Payment::where('user_uuid', $user->uuid)->sum('amount'),
                'approved_amount' => Payment::where('user_uuid', $user->uuid)->approved()->sum('amount'),
                'pending_amount' => Payment::where('user_uuid', $user->uuid)->pending()->sum('amount'),
                'conversion_stats' => [
                    'bitcoin' => Payment::where('user_uuid', $user->uuid)
                        ->where('conversion_currency', 'bitcoin')
                        ->sum('conversion_amount'),
                    'ethereum' => Payment::where('user_uuid', $user->uuid)
                        ->where('conversion_currency', 'ethereum')
                        ->sum('conversion_amount'),
                    'btc' => Payment::where('user_uuid', $user->uuid)
                        ->where('conversion_currency', 'btc')
                        ->sum('conversion_amount'),
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Payment statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment approval status (User can only view, not approve)
     */
    public function getPaymentStatus(string $uuid): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $payment = Payment::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->first();

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'uuid' => $payment->uuid,
                    'amount' => $payment->amount,
                    'is_approved' => $payment->is_approved,
                    'status' => $payment->is_approved ? 'Approved' : 'Pending Review',
                    'created_at' => $payment->created_at,
                    'updated_at' => $payment->updated_at
                ],
                'message' => 'Payment status retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment status: ' . $e->getMessage()
            ], 500);
        }
    }
}
