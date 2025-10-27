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
use OpenApi\Attributes as OA;

class OtpController extends Controller
{
    protected $otpService;
    protected $walletService;

    public function __construct(OtpService $otpService, WalletService $walletService)
    {
        $this->otpService = $otpService;
        $this->walletService = $walletService;
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/otp/send",
     *     summary="Send OTP",
     *     description="Send OTP to user via SMS",
     *     operationId="sendOtp",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="User contact information",
     *         @OA\JsonContent(
     *             required={"identity", "channel"},
     *             @OA\Property(property="identity", type="string", example="+989123456789", description="User's phone number"),
     *             @OA\Property(property="channel", type="string", enum={"sms"}, example="sms", description="Channel to send OTP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true, description="Operation status"),
     *             @OA\Property(property="token", type="string", example="667c7641-1921-42ea-966c-cb7766bdfc86", description="Unique token for OTP verification"),
     *             @OA\Property(property="message", type="string", example="OTP sent.", description="Response message")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request")
     * )
     */
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

    /**
     * @OA\Post(
     *     path="/api/v1/auth/otp/verify",
     *     summary="Verify OTP",
     *     description="Verify OTP sent to user",
     *     operationId="verifyOtp",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="OTP verification data",
     *         @OA\JsonContent(
     *             required={"token", "code"},
     *             @OA\Property(property="token", type="string", example="667c7641-1921-42ea-966c-cb7766bdfc86", description="Unique token for OTP verification"),
     *             @OA\Property(property="code", type="integer", example=123456, description="OTP code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             oneOf={
     *                 @OA\Schema(
     *                     description="Response when user has an existing account",
     *                     @OA\Property(property="status", type="boolean", example=true, description="Operation status"),
     *                     @OA\Property(property="message", type="string", example="OTP verified. You are now logged in.", description="Response message"),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=2, description="User ID"),
     *                         @OA\Property(property="first_name", type="string", example="نام", description="User's first name"),
     *                         @OA\Property(property="last_name", type="string", example="نام خانوادگی", description="User's last name"),
     *                         @OA\Property(property="father_name", type="string", example="نام پدر", description="User's father name"),
     *                         @OA\Property(property="phone_number", type="string", example="+989123456789", description="User's phone number"),
     *                         @OA\Property(property="national_code", type="string", example="0012345678", description="User's national code"),
     *                         @OA\Property(property="passport_number", type="string", nullable=true, example=null, description="User's passport number"),
     *                         @OA\Property(property="passport_expiry_date", type="string", nullable=true, example=null, description="Passport expiry date"),
     *                         @OA\Property(property="is_verified", type="integer", example=1, description="Verification status"),
     *                         @OA\Property(property="birth_date", type="string", example="1310/06/30", description="User's birth date"),
     *                         @OA\Property(property="image", type="string", example="base64image", description="User's profile image in base64"),
     *                         @OA\Property(property="email", type="string", nullable=true, example=null, description="User's email"),
     *                         @OA\Property(property="email_verified_at", type="string", nullable=true, example=null, description="Email verification timestamp"),
     *                         @OA\Property(property="locale", type="string", example="fa", description="User's locale"),
     *                         @OA\Property(property="remember_token", type="string", nullable=true, example=null, description="Remember token"),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-15T10:32:14.000000Z", description="Account creation timestamp"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-15T10:32:14.000000Z", description="Account update timestamp")
     *                     ),
     *                     @OA\Property(property="auth_token", type="string", example="2|iKaaaaaaaaaaaaaaaaaaaa6cU1qr5m2SCBk1TyWd934ae54c", description="Authentication token"),
     *                     @OA\Property(property="code", type="integer", example=200, description="Response code")
     *                 ),
     *                 @OA\Schema(
     *                     description="Response when user does not have an account",
     *                     @OA\Property(property="status", type="boolean", example=true, description="Operation status"),
     *                     @OA\Property(property="message", type="string", example="OTP verified. Please provide birth date and national code to complete registration.", description="Response message"),
     *                     @OA\Property(property="otp_token", type="string", example="0762d415-6764-4165-be65-ce73927525ad", description="Token for next step"),
     *                     @OA\Property(property="next_step", type="string", example="identity_verification", description="Next step in registration process"),
     *                     @OA\Property(property="code", type="integer", example=200, description="Response code")
     *                 )
     *             }
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false, description="Operation status"),
     *             @OA\Property(property="message", type="string", example="Invalid OTP code.", description="Error message"),
     *             @OA\Property(property="code", type="integer", example=400, description="Response code")
     *         )
     *     )
     * )
     */
    public function verify(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'code' => 'required|numeric',
        ]);

        $result = $this->otpService->verify($data['token'], $data['code']);
        return response()->json($result, $result['code']);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/otp/verify-identity",
     *     summary="Verify Identity",
     *     description="Verify user's identity using OTP and national code",
     *     operationId="verifyIdentity",
     *     tags={"Auth"},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Identity verification data",
     *         @OA\JsonContent(
     *             required={"token", "birth_date", "national_code"},
     *             @OA\Property(property="token", type="string", example="667c7641-1921-42ea-966c-cb7766bdfc86", description="Unique token for OTP verification"),
     *             @OA\Property(property="birth_date", type="string", format="date", example="1310/06/30", description="User's birth date"),
     *             @OA\Property(property="national_code", type="string", example="0012345678", description="User's national code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true, description="Operation status"),
     *             @OA\Property(property="message", type="string", example="Registration and identity verification completed successfully.", description="Response message"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="auth_token", type="string", example="2|iKaaaaaaaaaaaaaaaaaaaa6cU1qr5m2SCBk1TyWd934ae54c", description="Authentication token")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Invalid request")
     * )
     */
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
