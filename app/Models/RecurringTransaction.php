<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecurringTransaction extends Model
{
    protected $fillable = [
        'user_id', 'wallet_id', 'destination_wallet_id', 'category_id',
        'amount', 'type', 'note', 'day_of_month', 'last_processed_at'
    ];

    public function wallet() { return $this->belongsTo(Wallet::class, 'wallet_id'); }
    public function destinationWallet() { return $this->belongsTo(Wallet::class, 'destination_wallet_id'); }
    public function category() { return $this->belongsTo(Category::class); }
}
