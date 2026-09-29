<?php

return [

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp (WAHA)
    |--------------------------------------------------------------------------
    | 'enabled' default true supaya perilaku lama tidak berubah.
    | Set WAHA_ENABLED=false kalau service WAHA sedang tidak dipakai — semua
    | pengiriman notifikasi dilewati tanpa menulis error ke log.
    */
    'waha' => [
        'enabled' => env('WAHA_ENABLED', true),
        'url' => env('WAHA_URL', 'http://localhost:3000'),
        'api_key' => env('WAHA_API_KEY'),
        'session' => env('WAHA_SESSION', 'default'),
        // ⚡ Dipakai sebagai batas tunggu per pesan. Sebelumnya 15 detik: kalau WAHA
        // menggantung, 6 penerima bisa membuat halaman tertahan sampai 90 detik.
        'timeout' => env('WAHA_TIMEOUT', 5),
        // ⚡ Kirim notifikasi SETELAH halaman dikirim ke pengguna (respons jadi instan).
        // Setel false kalau ingin kembali mengirim di dalam request.
        'after_response' => env('WAHA_AFTER_RESPONSE', true),
    ],

];
