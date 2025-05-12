<?php

return [
    'default' => env('SMS_PROVIDER', 'melipayamak'), // Default SMS provider

    'providers' => [
        'smsir' => [
            'api_key' => env('SMSIR_TOKEN'),
            'verify_template' => env('SMSIR_VERIFY'),
            'endpoint' => 'https://api.sms.ir/v1/send/verify',
        ],
        'melipayamak' => [
            'api_key' => env('MELIPAYAMAK_TOKEN'),
            'verify_template' => env('MELIPAYAMAK_VERIFY'),
            'endpoint' => 'https://console.melipayamak.com/api/send/shared',
        ],
    ],
];
