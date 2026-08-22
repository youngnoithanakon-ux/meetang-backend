<?php

namespace App\Http\Controllers;

use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecurringTransactionController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $request->user()->recurringTransactions()->with(['wallet', 'destinationWallet', 'category'])->get()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'wallet_id' => 'required|exists:wallets,id',
            'destination_wallet_id' => 'required_if:type,transfer|exists:wallets,id|different:wallet_id',
            'category_id' => 'nullable|exists:categories,id',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:income,expense,transfer',
            'day_of_month' => 'required|integer|min:1|max:31',
            'note' => 'nullable|string'
        ]);

        $recurring = $request->user()->recurringTransactions()->create($request->all());
        return response()->json($recurring->load(['wallet', 'destinationWallet', 'category']), 201);
    }

    public function destroy(Request $request, RecurringTransaction $recurringTransaction)
    {
        if ($recurringTransaction->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        $recurringTransaction->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function process(Request $request)
    {
        $user = $request->user();
        $recurrings = $user->recurringTransactions;
        $now = Carbon::now();
        $processedCount = 0;

        foreach ($recurrings as $recurring) {
            // Check if already processed this month and year
            if ($recurring->last_processed_at) {
                $lastProcessed = Carbon::parse($recurring->last_processed_at);
                if ($lastProcessed->month == $now->month && $lastProcessed->year == $now->year) {
                    continue;
                }
            }

            // Determine the target date for this month
            $targetDay = min($recurring->day_of_month, $now->daysInMonth);
            if ($now->day >= $targetDay) {
                DB::beginTransaction();
                try {
                    // Create transaction
                    $transaction = $user->transactions()->create([
                        'wallet_id' => $recurring->wallet_id,
                        'destination_wallet_id' => $recurring->destination_wallet_id,
                        'category_id' => $recurring->category_id,
                        'amount' => $recurring->amount,
                        'type' => $recurring->type,
                        'note' => $recurring->note . ' (รายการประจำเดือน)',
                        'date' => Carbon::createFromDate($now->year, $now->month, $targetDay)->toDateTimeString()
                    ]);

                    // Update balances
                    $wallet = Wallet::find($recurring->wallet_id);
                    if ($recurring->type === 'income') {
                        $wallet->increment('balance', $recurring->amount);
                    } elseif ($recurring->type === 'expense') {
                        $wallet->decrement('balance', $recurring->amount);
                    } elseif ($recurring->type === 'transfer') {
                        $wallet->decrement('balance', $recurring->amount);
                        $destWallet = Wallet::find($recurring->destination_wallet_id);
                        if ($destWallet) $destWallet->increment('balance', $recurring->amount);
                    }

                    // Mark as processed
                    $recurring->update(['last_processed_at' => $now->toDateString()]);
                    
                    DB::commit();
                    $processedCount++;
                } catch (\Exception $e) {
                    DB::rollBack();
                }
            }
        }

        return response()->json(['message' => "Processed $processedCount recurring transactions."]);
    }
}
