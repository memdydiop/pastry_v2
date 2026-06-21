<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CinetPay API Configuration
    |--------------------------------------------------------------------------
    |
    | Site ID, API Key, and Secret Key from your CinetPay dashboard.
    | Set sandbox to true for testing with test phone numbers.
    |
    */
    'site_id' => env('CINETPAY_SITE_ID'),
    'api_key' => env('CINETPAY_API_KEY'),
    'secret_key' => env('CINETPAY_SECRET_KEY'),
    'sandbox' => env('CINETPAY_SANDBOX', true),
];
