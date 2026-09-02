<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // 1. Get Wallets
        $wallets = $user->wallets()->get();
        
        // 2. Get Transactions for the requested month/year (or current if not provided)
        $month = $request->input('month', Carbon::now()->month);
        $year = $request->input('year', Carbon::now()->year);
        
        $transactions = $user->transactions()
            ->with(['wallet', 'destinationWallet', 'category'])
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->latest('date')
            ->get();
            
        return response()->json([
            'wallets' => $wallets,
            'transactions' => $transactions
        ]);
    }
}
