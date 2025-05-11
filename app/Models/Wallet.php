<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'type', 'has_commitment'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function balances()
    {
        return $this->hasMany(WalletBalance::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'from_wallet_id');
    }
}