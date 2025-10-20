<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentDetails;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PaymentDetailsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $paymentDetails = PaymentDetails::all();
        return response()->json([
            'success' => true,
            'data' => $paymentDetails
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'bitcoin_address' => 'nullable|string|max:255',
            'bitcoin_instructions' => 'nullable|array',
            'ethereum_address' => 'nullable|string|max:255',
            'ethereum_instructions' => 'nullable|array',
            'usdt_address_TRC20' => 'nullable|string|max:255',
            'usdt_trc20_instructions' => 'nullable|array',
            'usdt_address_ERC20' => 'nullable|string|max:255',
            'usdt_erc20_instructions' => 'nullable|array'
        ]);

        $paymentDetails = PaymentDetails::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Payment details created successfully',
            'data' => $paymentDetails
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $paymentDetails = PaymentDetails::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $paymentDetails
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'bitcoin_address' => 'nullable|string|max:255',
            'bitcoin_instructions' => 'nullable|array',
            'ethereum_address' => 'nullable|string|max:255',
            'ethereum_instructions' => 'nullable|array',
            'usdt_address_TRC20' => 'nullable|string|max:255',
            'usdt_trc20_instructions' => 'nullable|array',
            'usdt_address_ERC20' => 'nullable|string|max:255',
            'usdt_erc20_instructions' => 'nullable|array'
        ]);

        $paymentDetails = PaymentDetails::findOrFail($id);
        $paymentDetails->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Payment details updated successfully',
            'data' => $paymentDetails
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $paymentDetails = PaymentDetails::findOrFail($id);
        $paymentDetails->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment details deleted successfully'
        ]);
    }
}
