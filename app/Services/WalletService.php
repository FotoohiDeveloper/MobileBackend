<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Wallet;
use App\Models\WalletBalance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class WalletService
{
    protected $minCommitmentRial = 1000000; // حداقل مبلغ تعهد (۱ میلیون تومان)

    // انتقال وجه بین کیف پول‌ها با شماره موبایل
    public function transfer($fromUserId, $toPhone, $amount, $currencyCode, $walletType)
    {
        $fromWallet = Wallet::where('user_id', $fromUserId)
            ->where('type', $walletType)
            ->firstOrFail();

        $fromBalance = $fromWallet->balances()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        if ($fromWallet->has_commitment) {
            $availableBalance = $fromBalance->balance - $fromBalance->committed_balance;
            if ($availableBalance < $amount) {
                throw new \Exception('موجودی قابل انتقال کافی نیست (بخشی از موجودی بلاک شده است)');
            }
        } elseif ($fromBalance->balance < $amount) {
            throw new \Exception('موجودی کافی نیست');
        }

        $toUser = User::where('phone', $toPhone)->firstOrFail();
        $toWallet = Wallet::where('user_id', $toUser->id)
            ->where('type', $walletType)
            ->firstOrFail();

        $toBalance = $toWallet->balances()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        // فقط کیف پول‌های ارزی می‌توانند انتقال ارز داشته باشند
        if ($fromWallet->type !== 'foreign' || $toWallet->type !== 'foreign') {
            if ($fromBalance->currency->code !== 'IRR' || $toBalance->currency->code !== 'IRR') {
                throw new \Exception('فقط کیف پول‌های ارزی می‌توانند ارزهای غیرریالی انتقال دهند');
            }
        }

        DB::transaction(function () use ($fromWallet, $toWallet, $fromBalance, $toBalance, $amount) {
            $fromBalance->balance -= $amount;
            $toBalance->balance += $amount;
            $fromBalance->save();
            $toBalance->save();

            Transaction::create([
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'amount' => $amount,
                'type' => 'transfer',
                'description' => "انتقال $amount {$fromBalance->currency->code} از {$fromWallet->user->phone} به {$toWallet->user->phone}",
            ]);
        });

        return true;
    }

    // تبدیل ارز در کیف پول ارزی
    public function convertCurrency($userId, $fromCurrencyCode, $toCurrencyCode, $amount)
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->firstOrFail();

        $fromBalance = $wallet->balances()
            ->whereHas('currency', fn($q) => $q->where('code', $fromCurrencyCode))
            ->firstOrFail();

        $toBalance = $wallet->balances()
            ->whereHas('currency', fn($q) => $q->where('code', $toCurrencyCode))
            ->first();

        // اگر ارز مقصد وجود ندارد، آن را ایجاد کن
        if (!$toBalance) {
            $toCurrency = Currency::where('code', $toCurrencyCode)->firstOrFail();
            $toBalance = $wallet->balances()->create([
                'currency_id' => $toCurrency->id,
                'balance' => 0,
            ]);
        }

        $availableBalance = $wallet->has_commitment ? $fromBalance->balance - $fromBalance->committed_balance : $fromBalance->balance;
        if ($availableBalance < $amount) {
            throw new \Exception('موجودی قابل تبدیل کافی نیست (بخشی از موجودی بلاک شده است)');
        }

        $exchangeRate = $this->getExchangeRate($fromCurrencyCode, $toCurrencyCode);
        $convertedAmount = $amount * $exchangeRate;

        DB::transaction(function () use ($wallet, $fromBalance, $toBalance, $amount, $convertedAmount) {
            $fromBalance->balance -= $amount;
            $toBalance->balance += $convertedAmount;
            $fromBalance->save();
            $toBalance->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'to_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'conversion',
                // 'description' => "تبدیل $amount $fromCurrencyCode به $convertedAmount $toCurrencyCode",
            ]);
        });

        return true;
    }

    // ایجاد یا به‌روزرسانی قرارداد تعهد
    public function commitPayment($userId, $currencyCode, $amount)
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->firstOrFail();

        if ($wallet->has_commitment) {
            throw new \Exception('شما قبلاً یک قرارداد تعهد دارید. فقط می‌توانید مبلغ را افزایش دهید.');
        }

        $balance = $wallet->balances()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        $exchangeRate = $this->getExchangeRate($currencyCode, 'IRR');
        $amountRial = $amount * $exchangeRate;

        if ($amountRial < $this->minCommitmentRial) {
            throw new \Exception('حداقل مبلغ تعهد باید معادل ۱ میلیون تومان باشد');
        }

        if ($balance->balance < $amount) {
            throw new \Exception('موجودی کافی برای تعهد نیست');
        }

        DB::transaction(function () use ($wallet, $balance, $amount) {
            $balance->committed_balance = $amount;
            $balance->save();
            $wallet->has_commitment = true;
            $wallet->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'commitment',
                'description' => "تعهد $amount {$balance->currency->code} معادل " . ($amount * $this->getExchangeRate($balance->currency->code, 'IRR')) . " IRR",
            ]);
        });

        return true;
    }

    // افزایش مبلغ تعهد
    public function increaseCommitment($userId, $currencyCode, $additionalAmount)
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->firstOrFail();

        if (!$wallet->has_commitment) {
            throw new \Exception('ابتدا باید یک قرارداد تعهد ایجاد کنید');
        }

        $balance = $wallet->balances()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        if ($balance->balance < $additionalAmount + $balance->committed_balance) {
            throw new \Exception('موجودی کافی برای افزایش تعهد نیست');
        }

        DB::transaction(function () use ($wallet, $balance, $additionalAmount) {
            $balance->committed_balance += $additionalAmount;
            $balance->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'amount' => $additionalAmount,
                'type' => 'commitment_increase',
                'description' => "افزایش تعهد به میزان $additionalAmount {$balance->currency->code}",
            ]);
        });

        return true;
    }

    // مصرف مبلغ تعهد
    public function consumeCommitment($userId, $currencyCode, $amountRial)
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->firstOrFail();

        if (!$wallet->has_commitment) {
            throw new \Exception('هیچ قرارداد تعهدی وجود ندارد');
        }

        $balance = $wallet->balances()
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        $exchangeRate = $this->getExchangeRate($currencyCode, 'IRR');
        $amountInCurrency = $amountRial / $exchangeRate;

        if ($amountInCurrency > $balance->committed_balance) {
            throw new \Exception('مبلغ تعهدی کافی نیست');
        }

        DB::transaction(function () use ($wallet, $balance, $amountInCurrency) {
            $balance->committed_balance -= $amountInCurrency;
            $balance->balance -= $amountInCurrency;
            if ($balance->committed_balance <= 0) {
                $wallet->has_commitment = false;
                $wallet->save();
            }
            $balance->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'amount' => $amountInCurrency,
                'type' => 'commitment_consume',
                'description' => "مصرف $amountInCurrency {$balance->currency->code} از تعهد",
            ]);
        });

        return true;
    }

    // دریافت نرخ لحظه‌ای ارز
    protected function getExchangeRate($fromCurrency, $toCurrency)
    {
        if ($fromCurrency === $toCurrency) {
            return 1;
        }

        $apiKey = env('EXCHANGE_RATE_API_KEY');
        $response = Http::get("https://api.exchangerate-api.com/v4/latest/{$fromCurrency}", [
            'access_key' => $apiKey,
        ]);

        if ($response->failed()) {
            throw new \Exception('خطا در دریافت نرخ ارز');
        }

        $rates = $response->json()['rates'];
        return $rates[$toCurrency] ?? throw new \Exception("نرخ برای $toCurrency یافت نشد");
    }

    // انتقال از خزانه داری به کیف پول
    public function transferFromTreasury($amount, $currencyId, $description, $wallet_id)
    {
        $wallet = Wallet::find($wallet_id);
        if (!$wallet) {
            throw new \Exception('کیف پول یافت نشد');
        }

        $currency = Currency::find($currencyId);
        if (!$currency) {
            throw new \Exception('ارز یافت نشد');
        }

        $balance = WalletBalance::where('wallet_id', $wallet->id)
            ->where('currency_id', $currencyId)
            ->first();
        if (!$balance) {
            $balance = WalletBalance::create([
                'wallet_id' => $wallet->id,
                'currency_id' => $currencyId,
                'balance' => 0,
            ]);
        }

        $treasuryBalance = WalletBalance::where('wallet_id', 1)
            ->where('currency_id', $currencyId)
            ->first();
        if ($treasuryBalance->balance < $amount) {
            throw new \Exception('موجودی خزانه داری کافی نیست');
        }
        if ($amount <= 0) {
            throw new \Exception('مبلغ باید بزرگتر از صفر باشد');
        }

        DB::transaction(function () use ($wallet, $balance, $amount, $description, $treasuryBalance) {
            $balance->balance += $amount;
            $balance->save();

            $treasuryBalance->balance -= $amount;
            $treasuryBalance->save();

            Transaction::create([
                'from_wallet_id' => 1, // خزانه داری
                'to_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'transfer',
                'description' => $description,
            ]);
        });

        return true;
    }

    // انتقال از کیف پول به خزانه داری
    public function transferToTreasury($amount, $currencyId, $description, $wallet_id)
    {
        $wallet = Wallet::find($wallet_id);
        if (!$wallet) {
            throw new \Exception('کیف پول یافت نشد');
        }

        $currency = Currency::find($currencyId);
        if (!$currency) {
            throw new \Exception('ارز یافت نشد');
        }

        $balance = WalletBalance::where('wallet_id', $wallet->id)
            ->where('currency_id', $currencyId)
            ->first();
        if (!$balance) {
            throw new \Exception('موجودی کیف پول کافی نیست');
        }

        if ($balance->balance < $amount) {
            throw new \Exception('موجودی کیف پول کافی نیست');
        }
        if ($amount <= 0) {
            throw new \Exception('مبلغ باید بزرگتر از صفر باشد');
        }

        DB::transaction(function () use ($wallet, $balance, $amount, $description) {
            $balance->balance -= $amount;
            $balance->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'to_wallet_id' => 1, // خزانه داری
                'amount' => $amount,
                'type' => 'transfer',
                'description' => $description,
            ]);
        });

        return true;
    }

    // انتقال بین کیف پول‌ها
    public function transferTransaction($fromWallet_id, $toWallet_id, $amount, $currencyId, $description = null)
    {
        $fromWallet = Wallet::find($fromWallet_id);
        if (!$fromWallet) {
            return [
                "status" => false,
                "code" => 1,
                "message" => "Origin wallet not found"
            ];
        }

        $toWallet = Wallet::find($toWallet_id);
        if (!$toWallet) {
            return [
                "status" => false,
                "code" => 2,
                "message" => "Destination wallet not found"
            ];
        }

        $currency = Currency::find($currencyId);
        if (!$currency) {
            return [
                "status" => false,
                "code" => 3,
                "message" => "Currency not found"
            ];
        }

        $fromBalance = WalletBalance::where('wallet_id', $fromWallet->id)
            ->where('currency_id', $currencyId)
            ->first();

        if (!$fromBalance) {
            return [
                "status" => false,
                "code" => 4,
                "message" => "Origin wallet doses not have this currency"
            ];
        }

        if ($fromBalance->balance < $amount) {
            return [
                "status" => false,
                "code" => 5,
                "message" => "Origin wallet does not have enough balance"
            ];
        }

        $toBalance = WalletBalance::where('wallet_id', $toWallet->id)
            ->where('currency_id', $currencyId)
            ->first();

        if (!$toBalance) {
            $toBalance = WalletBalance::create([
                'wallet_id' => $toWallet->id,
                'currency_id' => $currencyId,
                'balance' => 0,
            ]);
        }

        DB::transaction(function () use ($fromBalance, $toBalance, $amount, $description) {
            $fromBalance->balance -= $amount;
            $toBalance->balance += $amount;
            $fromBalance->save();
            $toBalance->save();

            Transaction::create([
                'from_wallet_id' => $fromBalance->wallet_id,
                'to_wallet_id' => $toBalance->wallet_id,
                'amount' => $amount,
                'type' => 'transfer',
                'description' => $description,
            ]);
        });

        $fromWalletOwner = $fromWallet->user;
        $fromWalletOwnerFullName = $fromWalletOwner->first_name . ' ' . $fromWalletOwner->last_name;

        $toWalletOwner = $fromWallet->user;
        $toWalletOwnerFullName = $toWalletOwner->first_name . ' ' . $toWalletOwner->last_name;

        $currencySymbol = $currency->symbol;

        if ($fromWalletOwner->id == 1) {
            $toWallet->user->addNotification("Treasury trasfer", "واریز وجه", "مبلغ $amount $currencySymbol بابت $description از طرف خزانه داری به کیف پول شما واریز شد");
        } else {
            $fromWallet->user->addNotification("transfer", "انتقال وجه", "مبلغ $amount $currencySymbol بابت $description از کیف پول شما به کیف پول $toWalletOwnerFullName واریز شد");
            $toWallet->user->addNotification("transfer", "انتقال وجه", "مبلغ $amount $currencySymbol بابت $description از کیف پول $fromWalletOwnerFullName به کیف پول شما واریز شد");
        }

        return [
            "status" => true,
            "code" => 0,
            "message" => "Transaction created successfully"
        ];
    }
}
