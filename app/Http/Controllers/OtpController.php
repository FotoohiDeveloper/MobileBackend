<?php

namespace App\Http\Controllers;

use App\Models\Currency;
use App\Models\Otp;
use App\Services\OtpService;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class OtpController extends Controller
{
    protected $otpService;
    protected $walletService;

    public function __construct(OtpService $otpService, WalletService $walletService)
    {
        $this->otpService = $otpService;
        $this->walletService = $walletService;
    }

    public function send(Request $request)
    {
        $data = $request->validate([
            'identity' => [
                'required',
                'string',
                Rule::when($request->channel === 'email', 'email', 'regex:/^\+?[1-9]\d{1,14}$/'),
            ],
            'channel' => 'required|in:sms,email',
        ]);

        try {
            $token = $this->otpService->generate(
                $data['identity'],
                $data['channel']
            );
            return response()->json(['status' => true, 'token' => $token, 'message' => 'OTP sent.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'code' => 'required|numeric',
        ]);

        $result = $this->otpService->verify($data['token'], $data['code']);
        return response()->json($result, $result['code']);
    }

    public function verifyIdentity(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'birth_date' => 'required|date_format:Y/m/d',
            'national_code' => 'required|string|regex:/^[0-9]{10}$/',
        ]);

        // بررسی OTP
        $otp = Otp::where('token', $data['token'])->where('is_verified', true)->first();
        if (!$otp) {
            return response()->json(['status' => false, 'message' => 'Invalid or expired OTP token.'], 422);
        }

        $normalizedIdentity = $otp->identity;
        if (str_starts_with($otp->identity, '+98')) {
            $normalizedIdentity = '0' . substr($otp->identity, 3);
        }
        try {
            // احراز هویت شاهکار (Shohkar)
            $shahkarResponce = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('ZOHAL_API_TOKEN'),
            ])->post('https://service.zohal.io/api/v0/services/inquiry/shahkar', [
                'mobile' => $normalizedIdentity,
                'national_code' => $data['national_code'],
            ]);


            if ($shahkarResponce->failed() || $shahkarResponce->json('result') !== 1 || !$shahkarResponce->json()['response_body']['data']['matched']) {
                Log::error('Shahkar verification failed', [
                    'national_code' => $data['national_code'],
                    'response' => $shahkarResponce->json(),
                ]);
                return response()->json(['status' => false, 'message' => 'Shahkar verification failed.'], 400);
            }

            // استعلام اطلاعات هویتی با تصویر کارت ملی
            $identityResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('ZOHAL_API_TOKEN'),
            ])->post('https://service.zohal.io/api/v0/services/inquiry/national_identity_image', [
                'birth_date' => $data['birth_date'],
                'national_code' => $data['national_code'],
            ]);

            if ($identityResponse->failed() || $identityResponse->json('result') !== 1 || !$identityResponse->json()['response_body']['data']['matched']) {
                Log::error('National identity verification failed', [
                    'national_code' => $data['national_code'],
                    'response' => $identityResponse->json(),
                ]);
                return response()->json(['status' => false, 'message' => 'National identity verification failed.'], 400);
            }

            $identityData = $identityResponse->json('response_body.data');

            // بررسی وجود کاربر
            $user = User::where('phone_number', $otp->identity)
                ->orWhere('email', $otp->identity)
                ->first();

            if ($user) {
                return response()->json(['status' => false, 'message' => 'User already exists.'], 400);
            }

            // ثبت‌نام کاربر جدید
            $user = User::create([
                'first_name' => $identityData['first_name'],
                'last_name' => $identityData['last_name'],
                'father_name' => $identityData['father_name'],
                'phone_number' => $otp->identity,
                'national_code' => $data['national_code'],
                'is_verified' => true,
                'birth_date' => $data['birth_date'],
                'image' => $identityData['image'],
            ]);

            // ایجاد کیف پول‌ها
            $user->createDefaultWallets();
            $wallets = $user->wallets();

            $citizenWallet = $wallets->where('type', 'citizen')->first();

            $irr = Currency::where('code', 'IRR')->first();

            $this->walletService->transferTransaction(2, $citizenWallet->id, 100000, $irr->id, 'شارژ هدیه');

            // صدور توکن Sanctum
            $authToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Registration and identity verification completed successfully.',
                'user' => $user,
                'auth_token' => $authToken,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Identity verification error: ' . $e->getMessage(), [
                'national_code' => $data['national_code'],
            ]);
            return response()->json(['status' => false, 'message' => 'Identity verification failed: ' . $e->getMessage()], 500);
        }
    }
}
