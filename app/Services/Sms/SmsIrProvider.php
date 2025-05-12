<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;

class SmsIrProvider implements SmsProvider
{
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function send(string $mobile, int $code): bool
    {
        $response = Http::withHeaders(['X-API-KEY' => $this->config['api_key']])
            ->post($this->config['endpoint'], [
                'mobile' => $mobile,
                'templateId' => $this->config['verify_template'],
                'parameters' => [['name' => 'CODE', 'value' => $code]],
            ]);

        
        return $response->successful();
    }
}
