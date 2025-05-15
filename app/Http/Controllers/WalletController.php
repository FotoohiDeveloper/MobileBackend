<?php

namespace App\Http\Controllers;

use App\Models\WalletBalance;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class WalletController extends Controller
{
    protected $walletService;
    protected $user;

    public function __construct(WalletService $walletService, Request $request)
    {
        $this->walletService = $walletService;
        $this->user = $request->user();
    }

    public function index(Request $request)
    {
        $wallets = $request->user()->wallets()->get();
        return response()->json($wallets);
    }

    public function transfer(Request $request)
    {
        try {
            $data = $request->validate([
                'to_phone' => 'required|string',
                'amount' => 'required|numeric|min:0.01',
                'currency_code' => 'required|string|exists:currencies,code',
                'wallet_type' => 'required|in:citizen,normal,foreign',
            ]);

            $this->walletService->transfer(
                $request->user()->id,
                $data['to_phone'],
                $data['amount'],
                $data['currency_code'],
                $data['wallet_type']
            );

            return response()->json(['message' => Lang::get('messages.transfer_success')]);
        } catch (\Exception $e) {
            return response()->json(['error' => Lang::get('messages.insufficient_balance')], 400);
        }
    }

    public function convert(Request $request)
    {
        try {
            $data = $request->validate([
                'from_currency_code' => 'required|string|exists:currencies,code',
                'to_currency_code' => 'required|string|exists:currencies,code',
                'amount' => 'required|numeric|min:0.01',
            ]);

            $this->walletService->convertCurrency(
                $request->user()->id,
                $data['from_currency_code'],
                $data['to_currency_code'],
                $data['amount']
            );

            return response()->json(['message' => Lang::get('messages.convert_success')]);
        } catch (\Exception $e) {
            return response()->json(['error' => Lang::get('messages.insufficient_balance')], 400);
        }
    }

    public function commit(Request $request)
    {
        try {
            $data = $request->validate([
                'currency_code' => 'required|string|exists:currencies,code',
                'amount' => 'required|numeric|min:0.01',
            ]);

            $this->walletService->commitPayment(
                $request->user()->id,
                $data['currency_code'],
                $data['amount']
            );

            return response()->json(['message' => Lang::get('messages.commit_success')]);
        } catch (\Exception $e) {
            return match ($e->getMessage()) {
                'موجودی کافی برای تعهد نیست' => response()->json(['error' => Lang::get('messages.insufficient_balance')], 400),
                'شما قبلاً یک قرارداد تعهد دارید. فقط می‌توانید مبلغ را افزایش دهید.' => response()->json(['error' => Lang::get('messages.existing_commitment')], 400),
                'حداقل مبلغ تعهد باید معادل ۱ میلیون تومان باشد' => response()->json(['error' => Lang::get('messages.min_commitment')], 400),
                default => response()->json(['error' => $e->getMessage()], 400),
            };
        }
    }

    public function increaseCommitment(Request $request)
    {
        try {
            $data = $request->validate([
                'currency_code' => 'required|string|exists:currencies,code',
                'additional_amount' => 'required|numeric|min:0.01',
            ]);

            $this->walletService->increaseCommitment(
                $request->user()->id,
                $data['currency_code'],
                $data['additional_amount']
            );

            return response()->json(['message' => Lang::get('messages.increase_commit_success')]);
        } catch (\Exception $e) {
            return response()->json(['error' => Lang::get('messages.insufficient_balance')], 400);
        }
    }

    public function consumeCommitment(Request $request)
    {
        try {
            $data = $request->validate([
                'currency_code' => 'required|string|exists:currencies,code',
                'amount_rial' => 'required|numeric|min:0.01',
            ]);

            $this->walletService->consumeCommitment(
                $request->user()->id,
                $data['currency_code'],
                $data['amount_rial']
            );

            return response()->json(['message' => Lang::get('messages.consume_commit_success')]);
        } catch (\Exception $e) {
            return match ($e->getMessage()) {
                'هیچ قرارداد تعهدی وجود ندارد' => response()->json(['error' => Lang::get('messages.no_commitment')], 400),
                'مبلغ تعهدی کافی نیست' => response()->json(['error' => Lang::get('messages.insufficient_balance')], 400),
                default => response()->json(['error' => $e->getMessage()], 400),
            };
        }
    }

    public function mainReq(Request $request)
    {

        $totalBalance = $this->user->getTotalBalances();
        $recentTransactions = $this->user->recentTransactions();




        return response()->json([
            'status' => true,
            'message' => 'Wallets retrieved successfully',
            'data' => [
                'wallets' => $totalBalance['wallets'],
                'total_balance' => $totalBalance['total_balance'],
                'recent_transactions' => $recentTransactions,
            ],
        ]);
    }


}
