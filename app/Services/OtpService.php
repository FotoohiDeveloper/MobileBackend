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
            default => throw new Exception("Unsupported SMS provider: $providerName"),
        };

        $this->smsProvider = new $providerClass($config);
    }

    public function generate(string $identity, string $channel, string $type, ?User $user = null): string
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
            'user_id' => $user?->id,
            'identity' => $identity,
            'channel' => $channel,
            'type' => $type,
            'code' => $code,
            'token' => $token,
            'user_ip' => request()->ip(),
            'expires_at' => now()->addMinutes(2),
        ]);

        try {
            if ($channel === 'sms') {
                if (!$this->smsProvider->send($identity, $code)) {
                    throw new Exception('Failed to send SMS');
                }
            } elseif ($channel === 'email') {
                Mail::raw("Your OTP code is: $code", function ($message) use ($identity) {
                    $message->to($identity)->subject('Your OTP Code');
                });
            }
        } catch (Exception $e) {
            Log::error('OTP sending failed: ' . $e->getMessage());
            throw new Exception('Unable to send OTP. Please try again.');
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
        return ['status' => true, 'message' => 'OTP verified.', 'otp' => $otp, 'code' => 200];
    }
}