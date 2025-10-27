<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use App\Services\Sms\SmsProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class OtpService
{
    protected $smsProvider;

    public function __construct()
    {
        $providerName = config('sms.default');
        $config = config("sms.providers.$providerName");

        $providerClass = match ($providerName) {
            'smsir' => \App\Services\Sms\SmsIrProvider::class,
            'kavenegar' => \App\Services\Sms\KavenegarProvider::class,
            'melipayamak' => \App\Services\Sms\MelipayamakProvider::class,
            default => throw new Exception("Unsupported SMS provider: $providerName"),
        };

        $this->smsProvider = new $providerClass($config);
    }

    public function generate(string $identity, string $channel): string
    {
        $recentOtp = Otp::where('identity', $identity)
            ->where('created_at', '>=', now()->subMinute())
            ->first();
        if ($recentOtp) {
            throw new Exception('Please wait before requesting another OTP.');
        }

        $code = random_int(100000, 999999);
        $token = Str::uuid();

        $otp = Otp::create([
            'identity' => $identity,
            'channel' => $channel,
            'code' => $code,
            'token' => $token,
            'user_ip' => request()->ip(),
            'expires_at' => now()->addMinutes(2),
        ]);

        try {
            if ($channel === 'sms') {
                if (!$this->smsProvider->send($identity, $code)) {
                    Log::error('SMS sending failed for provider: ' . config('sms.default'), [
                        'phone' => $identity,
                        'code' => $code,
                    ]);
                    throw new Exception('Failed to send SMS. Please check provider configuration or try again later.');
                }
            } elseif ($channel === 'email') {
                Mail::raw("Your OTP code is: $code", function ($message) use ($identity) {
                    $message->to($identity)->subject('Your OTP Code');
                });
            }
        } catch (Exception $e) {
            Log::error('OTP sending failed: ' . $e->getMessage(), [
                'channel' => $channel,
                'identity' => $identity,
                'provider' => config('sms.default'),
            ]);
            throw new Exception('Unable to send OTP: ' . $e->getMessage());
        }

        return $token;
    }

    public function verify(string $token, string $code): array
    {
        $otp = Otp::valid()->where('token', $token)->first();
        if (!$otp) {
            return ['status' => false, 'message' => 'OTP is expired or invalid.', 'code' => 422];
        }

        if ($otp->code !== (int)$code) {
            $otp->increment('attempts');
            if ($otp->attempts >= 3) {
                $otp->update(['expires_at' => now()]);
            }
            return ['status' => false, 'message' => 'Invalid OTP code.', 'code' => 422];
        }

        $otp->update(['is_verified' => true]);

        // بررسی وجود کاربر
        $user = User::where('phone_number', $otp->identity)
            ->orWhere('email', $otp->identity)
            ->first();

        if ($user) {
            // کاربر موجود: صدور توکن Sanctum
            $authToken = $user->createToken('auth_token')->plainTextToken;
            return [
                'status' => true,
                'message' => 'OTP verified. You are now logged in.',
                'user' => $user,
                'auth_token' => $authToken,
                'code' => 200,
            ];
        }

        // کاربر جدید: نیاز به احراز هویت هویتی
        return [
            'status' => true,
            'message' => 'OTP verified. Please provide birth date and national code to complete registration.',
            'otp_token' => $otp->token,
            'next_step' => 'identity_verification',
            'code' => 200,
        ];
    }
}

