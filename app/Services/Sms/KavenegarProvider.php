<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class KavenegarProvider implements SmsProvider
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $mobile, int $code): bool
    {
        $response = Http::post($this->config['endpoint'] . '/' . $this->config['api_key'] . '/verify/lookup.json', [
            'receptor' => $mobile,
            'template' => $this->config['verify_template'],
            'token' => $code,
        ]);

        return $response->successful();
    }
}