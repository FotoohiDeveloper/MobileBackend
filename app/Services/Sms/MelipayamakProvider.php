<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class MelipayamakProvider implements SmsProvider
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $mobile, int $code): bool
    {
        $response = Http::post($this->config['endpoint'] . '/' . $this->config['api_key'], [
            'bodyId' => $this->config['verify_template'],
            'to' => $mobile,
            'args' => [(string) $code]
        ]);

        return $response->successful();
    }
}
