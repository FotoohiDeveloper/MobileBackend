<?php

namespace App\Services;

use App\Models\Wallet;
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
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        if ($fromWallet->type === 'foreign' && $fromWallet->has_commitment) {
            $availableBalance = $fromWallet->balance - $fromWallet->committed_balance;
            if ($availableBalance < $amount) {
                throw new \Exception('موجودی قابل انتقال کافی نیست (بخشی از موجودی بلاک شده است)');
            }
        } elseif ($fromWallet->balance < $amount) {
            throw new \Exception('موجودی کافی نیست');
        }

        $toUser = User::where('phone', $toPhone)->firstOrFail();
        $toWallet = Wallet::where('user_id', $toUser->id)
            ->where('type', $walletType)
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        // فقط کیف پول‌های ارزی می‌توانند انتقال ارز داشته باشند
        if ($fromWallet->type !== 'foreign' || $toWallet->type !== 'foreign') {
            if ($fromWallet->currency->code !== 'IRR' || $toWallet->currency->code !== 'IRR') {
                throw new \Exception('فقط کیف پول‌های ارزی می‌توانند ارزهای غیرریالی انتقال دهند');
            }
        }

        DB::transaction(function () use ($fromWallet, $toWallet, $amount) {
            $fromWallet->balance -= $amount;
            $toWallet->balance += $amount;
            $fromWallet->save();
            $toWallet->save();

            Transaction::create([
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'amount' => $amount,
                'type' => 'transfer',
                'description' => "انتقال از {$fromWallet->user->phone} به {$toWallet->user->phone}",
            ]);
        });

        return true;
    }

    // تبدیل ارز فقط برای کیف پول ارزی
    public function convertCurrency($userId, $fromCurrencyCode, $toCurrencyCode, $amount)
    {
        $fromWallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->whereHas('currency', fn($q) => $q->where('code', $fromCurrencyCode))
            ->firstOrFail();

        $toWallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->whereHas('currency', fn($q) => $q->where('code', $toCurrencyCode))
            ->firstOrFail();

        $availableBalance = $fromWallet->has_commitment ? $fromWallet->balance - $fromWallet->committed_balance : $fromWallet->balance;
        if ($availableBalance < $amount) {
            throw new \Exception('موجودی قابل تبدیل کافی نیست (بخشی از موجودی بلاک شده است)');
        }

        $exchangeRate = $this->getExchangeRate($fromCurrencyCode, $toCurrencyCode);
        $convertedAmount = $amount * $exchangeRate;

        DB::transaction(function () use ($fromWallet, $toWallet, $amount, $convertedAmount) {
            $fromWallet->balance -= $amount;
            $toWallet->balance += $convertedAmount;
            $fromWallet->save();
            $toWallet->save();

            Transaction::create([
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
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
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        if ($wallet->has_commitment) {
            throw new \Exception('شما قبلاً یک قرارداد تعهد دارید. فقط می‌توانید مبلغ را افزایش دهید.');
        }

        $exchangeRate = $this->getExchangeRate($currencyCode, 'IRR');
        $amountRial = $amount * $exchangeRate;

        if ($amountRial < $this->minCommitmentRial) {
            throw new \Exception('حداقل مبلغ تعهد باید معادل ۱ میلیون تومان باشد');
        }

        if ($wallet->balance < $amount) {
            throw new \Exception('موجودی کافی برای تعهد نیست');
        }

        DB::transaction(function () use ($wallet, $amount) {
            $wallet->committed_balance = $amount;
            $wallet->has_commitment = true;
            $wallet->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'commitment',
                'description' => "تعهد $amount {$wallet->currency->code} معادل " . ($amount * $this->getExchangeRate($wallet->currency->code, 'IRR')) . " IRR",
            ]);
        });

        return true;
    }

    // افزایش مبلغ تعهد
    public function increaseCommitment($userId, $currencyCode, $additionalAmount)
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        if (!$wallet->has_commitment) {
            throw new \Exception('ابتدا باید یک قرارداد تعهد ایجاد کنید');
        }

        if ($wallet->balance < $additionalAmount + $wallet->committed_balance) {
            throw new \Exception('موجودی کافی برای افزایش تعهد نیست');
        }

        DB::transaction(function () use ($wallet, $additionalAmount) {
            $wallet->committed_balance += $additionalAmount;
            $wallet->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'amount' => $additionalAmount,
                'type' => 'commitment_increase',
                'description' => "افزایش تعهد به میزان $additionalAmount {$wallet->currency->code}",
            ]);
        });

        return true;
    }

    // مصرف مبلغ تعهد
    public function consumeCommitment($userId, $currencyCode, $amountRial)
    {
        $wallet = Wallet::where('user_id', $userId)
            ->where('type', 'foreign')
            ->whereHas('currency', fn($q) => $q->where('code', $currencyCode))
            ->firstOrFail();

        if (!$wallet->has_commitment) {
            throw new \Exception('هیچ قرارداد تعهدی وجود ندارد');
        }

        $exchangeRate = $this->getExchangeRate($currencyCode, 'IRR');
        $amountInCurrency = $amountRial / $exchangeRate;

        if ($amountInCurrency > $wallet->committed_balance) {
            throw new \Exception('مبلغ تعهدی کافی نیست');
        }

        DB::transaction(function () use ($wallet, $amountInCurrency) {
            $wallet->committed_balance -= $amountInCurrency;
            $wallet->balance -= $amountInCurrency;
            if ($wallet->committed_balance <= 0) {
                $wallet->has_commitment = false;
            }
            $wallet->save();

            Transaction::create([
                'from_wallet_id' => $wallet->id,
                'amount' => $amountInCurrency,
                'type' => 'commitment_consume',
                'description' => "مصرف $amountInCurrency {$wallet->currency->code} از تعهد",
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
}