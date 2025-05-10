<?php

namespace App\Http\Controllers;

use App\Services\OtpService;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OtpController extends Controller
{
    protected $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
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
            'type' => 'required|in:login,register,recovery',
        ]);

        $user = User::where('phone_number', $data['identity'])
            ->orWhere('email', $data['identity'])->first();

        if (in_array($data['type'], ['login', 'recovery']) && !$user) {
            return response()->json(['status' => false, 'message' => 'User not found.'], 404);
        }

        try {
            $token = $this->otpService->generate(
                $data['identity'],
                $data['channel'],
                $data['type'],
                $user
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
}