<?php

return [
    'sftp' => [
        'host' => env('FEED_SFTP_HOST'),
        'port' => env('FEED_SFTP_PORT', 2200),
        'username' => env('FEED_SFTP_USERNAME'),
        'password' => env('FEED_SFTP_PASSWORD'),
    ],

    /*
    | Legacy fallback "max(prix*)" is intentionally disabled by default.
    | Enable only if product mappings are incomplete during migration.
    */
    'allow_max_price_fallback' => env('FEED_ALLOW_MAX_PRICE_FALLBACK', false),
];
