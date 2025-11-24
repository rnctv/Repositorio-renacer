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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // === Google Maps (usado por AgendaController -> gmapsKey) ===
    'google' => [
        'maps_browser_key' => env('GOOGLE_MAPS_BROWSER_KEY', ''),
    ],

     /*
    |--------------------------------------------------------------------------
    | AutomatizadoVIP (BIG)
    |--------------------------------------------------------------------------
    | Forzamos el endpoint BIG y permitimos definir opcionalmente instance y sender.
    | Agrega también los flags de red/seguridad por si tu hosting requiere IPv4.
    */
    'autovip' => [
        'url'      => env('AUTOVIP_URL', 'https://big.automatizadovip.com/api/whatsapp/send'),
        'key'      => env('AUTOVIP_KEY', ''),

        // Opcionales pero MUY recomendados si tu cuenta tiene varias instancias/senders
        'instance' => env('AUTOVIP_INSTANCE', null),
        'sender'   => env('AUTOVIP_SENDER', null),

        // Networking
        'timeout'       => env('AUTOVIP_TIMEOUT', 20),
        'force_ipv4'    => env('AUTOVIP_FORCE_IPV4', true),
        'tls_insecure'  => env('AUTOVIP_TLS_INSECURE', false),
    ],

];