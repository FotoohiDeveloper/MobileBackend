<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Models\Transaction;

class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'first_name', 'last_name', 'father_name', 'phone_number', 'national_code',
        'passport_number', 'passport_expiry_date', 'is_verified', 'birth_date',
        'image', 'email', 'phone', 'password', 'locale'
    ];

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function createDefaultWallets($defaultIRR = 0, $defaultUSD = 0, $defaultEUR = 0, $defaultGBP = 0)
    {
        $irrCurrency = Currency::where('code', 'IRR')->firstOrFail();
        $usdCurrency = Currency::where('code', 'USD')->firstOrFail();
        $eurCurrency = Currency::where('code', 'EUR')->firstOrFail();
        $gbpCurrency = Currency::where('code', 'GBP')->firstOrFail();

        // کیف پول شهروندی
        $this->wallets()->create(['type' => 'citizen'])->balances()->create([
            'currency_id' => $irrCurrency->id,
            'balance' => 0,
        ]);

        // کیف پول ریالی عادی
        $this->wallets()->create(['type' => 'normal'])->balances()->create([
            'currency_id' => $irrCurrency->id,
            'balance' => $defaultIRR,
        ]);

        // کیف پول ارزی (چندمنظوره)
        $foreignWallet = $this->wallets()->create(['type' => 'foreign']);
        $foreignWallet->balances()->createMany([
            ['currency_id' => $usdCurrency->id, 'balance' => $defaultUSD],
            ['currency_id' => $eurCurrency->id, 'balance' => $defaultEUR],
            ['currency_id' => $gbpCurrency->id, 'balance' => $defaultGBP],
        ]);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications()
    {
        return $this->hasMany(Notification::class)->where('read', 0);
    }

    public function addNotification($type, $description, $message)
    {
        return $this->notifications()->create([
            'type' => $type,
            'description' => $description,
            'message' => $message,
            'read' => 0,
        ]);
    }

    public function recentTransactions()
    {
        $walletIds = $this->wallets()->pluck('id');

        $outTransactions = Transaction::whereIn('from_wallet_id', $walletIds)
            ->selectRaw('transactions.*, "out" as direction')
            ->with(['currency', 'fromWallet.user', 'toWallet.user']);

        $inTransactions = Transaction::whereIn('to_wallet_id', $walletIds)
            ->selectRaw('transactions.*, "in" as direction')
            ->with(['currency', 'fromWallet.user', 'toWallet.user']);

        return $outTransactions->union($inTransactions)
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();
    }

    public function getTotalBalances() {
        $balance = 0;
        foreach ($wallets = $this->wallets as $wallet) {
            foreach ($wallet->balances as $balanceItem) {
                $balance += $balanceItem->balance * $balanceItem->currency->price;
            }
        }

        $wallets->each(function ($wallet) {
            $wallet->balances->each(function ($balance) {
                $balance->currency;
            });
        });

        return [
            'total_balance' => $balance,
            'wallets' => $wallets,
        ];
    }
}
