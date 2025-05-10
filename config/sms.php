<?php

return [
    'default' => env('SMS_PROVIDER', 'smsir'), // Default SMS provider

    'providers' => [
        'smsir' => [
            'api_key' => env('SMSIR_TOKEN'),
            'verify_template' => env('SMSIR_VERIFY'),
            'endpoint' => 'https://api.sms.ir/v1/send/verify',
        ],
        // Add new providers here, e.g.:
        // 'kavenegar' => [
        //     'api_key' => env('KAVENEGAR_TOKEN'),
        //     'verify_template' => env('KAVENEGAR_VERIFY'),
        //     'endpoint' => 'https://api.kavenegar.com/v1/verify',
        // ],
    ],
];