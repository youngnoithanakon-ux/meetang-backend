<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->transactions()->with(['wallet', 'destinationWallet', 'category'])->latest('date');

        if ($request->has('month') && $request->has('year')) {
            $query->whereMonth('date', $request->month)
                  ->whereYear('date', $request->year);
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'wallet_id' => 'required|exists:wallets,id',
            'destination_wallet_id' => 'required_if:type,transfer|exists:wallets,id|different:wallet_id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense,transfer',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'image' => 'nullable|image|max:5120' // Max 5MB
        ]);

        $wallet = Wallet::where('id', $request->wallet_id)
                        ->where('user_id', $request->user()->id)
                        ->firstOrFail();

        $data = $request->except('image');

        if ($request->hasFile('image') && $request->file('image')->isValid()) {
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $filename = uniqid() . '_' . time() . '.' . $extension;
            
            // ใช้ Storage::put เพื่อดึงข้อมูลไฟล์โดยตรง (แก้บั๊ก tmp_name บนบาง OS)
            \Illuminate\Support\Facades\Storage::disk('public')->put('slips/' . $filename, $file->get());
            $data['image_path'] = 'slips/' . $filename;
        } elseif ($request->hasFile('image') && !$request->file('image')->isValid()) {
            return response()->json(['message' => 'ไฟล์รูปภาพมีขนาดใหญ่เกินไป หรือไม่สามารถอัปโหลดได้ (Max: 2MB)'], 400);
        }

        DB::beginTransaction();
        try {
            $transaction = $request->user()->transactions()->create($data);

            if ($transaction->type === 'income') {
                $wallet->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $wallet->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer') {
                $wallet->decrement('balance', $transaction->amount);
                
                $destWallet = Wallet::where('id', $request->destination_wallet_id)
                                    ->where('user_id', $request->user()->id)
                                    ->firstOrFail();
                $destWallet->increment('balance', $transaction->amount);
            }

            DB::commit();
            return response()->json($transaction->load(['wallet', 'destinationWallet', 'category']), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Transaction failed'], 500);
        }
    }

    public function update(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'wallet_id' => 'required|exists:wallets,id',
            'destination_wallet_id' => 'required_if:type,transfer|exists:wallets,id|different:wallet_id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense,transfer',
            'date' => 'required|date',
            'note' => 'nullable|string',
            'image' => 'nullable|image|max:5120'
        ]);

        DB::beginTransaction();
        try {
            // 1. Revert old transaction balances
            $oldWallet = $transaction->wallet;
            if ($transaction->type === 'income') {
                $oldWallet->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $oldWallet->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer') {
                $oldWallet->increment('balance', $transaction->amount);
                if ($transaction->destination_wallet_id) {
                    $oldDestWallet = Wallet::find($transaction->destination_wallet_id);
                    if ($oldDestWallet) $oldDestWallet->decrement('balance', $transaction->amount);
                }
            }

            // 2. Update transaction data
            $data = $request->except('image');
            if ($request->hasFile('image') && $request->file('image')->isValid()) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                $filename = uniqid() . '_' . time() . '.' . $extension;
                \Illuminate\Support\Facades\Storage::disk('public')->put('slips/' . $filename, $file->get());
                $data['image_path'] = 'slips/' . $filename;
            }
            $transaction->update($data);

            // 3. Apply new transaction balances
            $newWallet = Wallet::find($request->wallet_id);
            if ($transaction->type === 'income') {
                $newWallet->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $newWallet->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer') {
                $newWallet->decrement('balance', $transaction->amount);
                $newDestWallet = Wallet::find($request->destination_wallet_id);
                if ($newDestWallet) $newDestWallet->increment('balance', $transaction->amount);
            }

            DB::commit();
            return response()->json($transaction->load(['wallet', 'destinationWallet', 'category']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Update failed: ' . $e->getMessage()], 500);
        }
    }

    public function show(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($transaction->load(['wallet', 'category']));
    }

    public function destroy(Request $request, Transaction $transaction)
    {
        if ($transaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $wallet = $transaction->wallet;
            
            // Revert balance
            if ($transaction->type === 'income') {
                $wallet->decrement('balance', $transaction->amount);
            } elseif ($transaction->type === 'expense') {
                $wallet->increment('balance', $transaction->amount);
            } elseif ($transaction->type === 'transfer') {
                $wallet->increment('balance', $transaction->amount);
                if ($transaction->destination_wallet_id) {
                    $destWallet = Wallet::find($transaction->destination_wallet_id);
                    if ($destWallet) {
                        $destWallet->decrement('balance', $transaction->amount);
                    }
                }
            }

            $transaction->delete();
            
            DB::commit();
            return response()->json(['message' => 'Deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to delete transaction'], 500);
        }
    }
}
