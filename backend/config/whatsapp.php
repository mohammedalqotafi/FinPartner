<?php

return [
    'api_version' => env('WHATSAPP_API_VERSION', 'v22.0'),
    'token' => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '967'),
];