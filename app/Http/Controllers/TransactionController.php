<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Trasaction;

class TransactionController extends Controller
{
    public function index() {
        $transactions = Trasaction::all();
        return response()->json($transactions);
    }

    public function show($id) {
        $transaction = Trasaction::find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        return response()->json($transaction);
    }

    public function store(Request $request) {
        $transaction = Trasaction::create($request->all());
        return response()->json($transaction, 201);
    }

    public function update(Request $request, $id) {
        $transaction = Trasaction::find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        $transaction->update($request->all());
        return response()->json($transaction);
    }

    public function destroy($id) {
        $transaction = Trasaction::find($id);
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }
        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted']);
    }

}
