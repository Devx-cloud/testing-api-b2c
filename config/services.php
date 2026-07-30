<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'api' => [
        'key' => env('SECRET_API_KEY'),
    ],

    'rajaongkir' => [
        'base_url' => env('RAJA_ONGKIR_BASE_URL', 'https://rajaongkir.komerce.id'),
        'account_type' => env('RAJA_ONGKIR_ACCOUNT_TYPE', 'pro'),
        'api_key' => env('RAJA_ONGKIR_API_KEY'),
    ],

    // Harus sama dengan nusantaramall-b2c (Symfony) agar format invoice konsisten
    // lintas sistem, karena keduanya menulis ke tabel `order` yang sama.
    'invoice' => [
        'hashids_alphabet' => env('HASHIDS_ALPHABET', 'abcdefghijklmnopqrstuvwxyz1234567890'),
        'base_format' => env('BASE_INVOICE', 'BM-INVOICE/%s/%s/%s'),
    ],

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
