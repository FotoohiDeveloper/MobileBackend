<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['name', 'email', 'phone', 'password'];

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function createDefaultWallets()
    {
        $irrCurrency = Currency::where('code', 'IRR')->firstOrFail();
        $usdCurrency = Currency::where('code', 'USD')->firstOrFail();

        $this->wallets()->createMany([
            ['currency_id' => $irrCurrency->id, 'type' => 'citizen', 'balance' => 0],
            ['currency_id' => $irrCurrency->id, 'type' => 'normal', 'balance' => 0],
            ['currency_id' => $usdCurrency->id, 'type' => 'foreign', 'balance' => 0],
        ]);
    }
}