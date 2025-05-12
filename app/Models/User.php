<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = ['first_name', 'last_name', 'phone_number', 'national_code', 'passport_number', 'passport_expiry_date', 'is_verified', 'birth_date', 'image', 'email', 'phone', 'password', 'locale'];

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function createDefaultWallets()
    {
        $irrCurrency = Currency::where('code', 'IRR')->firstOrFail();
        $usdCurrency = Currency::where('code', 'USD')->firstOrFail();
        $eurCurrency = Currency::where('code', 'EUR')->firstOrFail();
        $gbpCurrency = Currency::where('code', 'GBP')->firstOrFail();

        // کیف پول شهروندی
        $this->wallets()->create(['type' => 'citizen'])->balances()->create([
            'currency_id' => $irrCurrency->id,
            'balance' => 100000,
        ]);

        // کیف پول ریالی عادی
        $this->wallets()->create(['type' => 'normal'])->balances()->create([
            'currency_id' => $irrCurrency->id,
            'balance' => 0,
        ]);

        // کیف پول ارزی (چندمنظوره)
        $foreignWallet = $this->wallets()->create(['type' => 'foreign']);
        $foreignWallet->balances()->createMany([
            ['currency_id' => $usdCurrency->id, 'balance' => 0],
            ['currency_id' => $eurCurrency->id, 'balance' => 0],
            ['currency_id' => $gbpCurrency->id, 'balance' => 0],
        ]);
    }
}
