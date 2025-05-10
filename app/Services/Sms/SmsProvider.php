<?php

namespace App\Services\Sms;

interface SmsProvider
{
    public function send(string $mobile, int $code): bool;
}