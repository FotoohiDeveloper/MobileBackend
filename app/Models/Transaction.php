<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $fillable = [
        'from_wallet_id', 'to_wallet_id', 'currency_id', 'amount',
        'type', 'description', 'created_at', 'updated_at'
    ];

    public function fromWallet()
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id');
    }

    public function toWallet()
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }
}
