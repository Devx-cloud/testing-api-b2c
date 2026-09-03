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

    // PPN mengikuti nusantaramall-b2c (config/parameters.php -> tax_value, dalam
    // persen). Hanya dikenakan kalau toko berstatus PKP (kolom store.is_pkp),
    // sama seperti CartController::checkProductWithTaxByPKP() di sana.
    'order' => [
        'tax_value' => env('ORDER_TAX_VALUE', 11),
    ],

    // Memori percakapan WhatsApp AI Agent (tabel wa_*). Tidak ada secret baru,
    // otentikasi endpoint tetap lewat services.api.key (header 'Token').
    'wa_memory' => [
        'gap_hours' => env('WA_THREAD_GAP_HOURS', 12),
        'default_char_budget' => env('WA_CONTEXT_CHAR_BUDGET', 6000),
        'default_recent_turns' => env('WA_HISTORY_RECENT_TURNS', 12),
        'prune_days' => env('WA_PRUNE_DAYS', 45),
        'prune_keep_min' => env('WA_PRUNE_KEEP_MIN', 40),
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
