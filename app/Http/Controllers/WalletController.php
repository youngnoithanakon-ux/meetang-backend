<?php

namespace App\Http\Controllers;

use App\Models\Wallet;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->wallets);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'balance' => 'nullable|numeric',
            'target_amount' => 'nullable|numeric|min:0'
        ]);

        $wallet = $request->user()->wallets()->create([
            'name' => $request->name,
            'balance' => $request->balance ?? 0,
            'target_amount' => $request->target_amount
        ]);

        return response()->json($wallet, 201);
    }

    public function show(Request $request, Wallet $wallet)
    {
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($wallet);
    }

    public function update(Request $request, Wallet $wallet)
    {
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'string|max:255',
            'balance' => 'numeric'
        ]);

        if ($request->has('balance') && $request->balance != $wallet->balance) {
            $diff = (float)$request->balance - (float)$wallet->balance;
            
            $request->user()->transactions()->create([
                'wallet_id' => $wallet->id,
                'type' => $diff > 0 ? 'income' : 'expense',
                'amount' => abs($diff),
                'date' => now(),
                'note' => 'ปรับปรุงยอดเงิน',
                'category_id' => null,
            ]);
        }

        $wallet->update($request->all());

        return response()->json($wallet);
    }

    public function destroy(Request $request, Wallet $wallet)
    {
        if ($wallet->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $wallet->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
