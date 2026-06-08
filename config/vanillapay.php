<?php

return [
    'environment'   => env('VANILLAPAY_ENV', 'preprod'),
    'client_id'     => env('VANILLAPAY_CLIENT_ID'),
    'client_secret' => env('VANILLAPAY_CLIENT_SECRET'),
    'key_secret'    => env('VANILLAPAY_KEY_SECRET'),
    'version'       => '2023-01-12', // VPI API Version
    'base_urls'     => [
        'preprod' => 'https://preprod.vanilla-pay.net',
        'prod'    => 'https://bo.vanilla-pay.net',
    ],
];
