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
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'sharemedata' => [
        'api_key'        => env('SHAREMEDATA_API_KEY'),
        'base_url'       => env('SHAREMEDATA_BASE_URL', 'https://gamma.sharemedata.com/api/calendar'),
        'patients_url'   => env('SHAREMEDATA_PATIENTS_URL', 'https://gamma.sharemedata.com/api'),
        'webhook_secret' => env('SHAREMEDATA_WEBHOOK_SECRET'),
    ],

    'insurance' => [
        'cache_enabled' => env('INSURANCE_VERIFY_CACHE_ENABLED', true),
        'cache_ttl'     => env('INSURANCE_VERIFY_CACHE_TTL', 3600),
    ],

    'alianza' => [
        'login_url'        => env('ALIANZA_LOGIN_URL', 'https://qualitynet.alianza.com.bo/ApiGateway'),
        'microservice_url' => env('ALIANZA_MICROSERVICE_URL', 'https://qualitynet.alianza.com.bo/ApiGateway'),
        'user'             => env('ALIANZA_USER', ''),
        'password'         => env('ALIANZA_PASS', ''),

        // Timeout (segundos) por petición HTTP a Alianza. Son llamadas REST cortas
        // (login + cobertura), 15s cubre latencia alta sin bloquear workers PHP.
        'timeout'          => env('ALIANZA_TIMEOUT', 15),

        // Si es true, loguea el accessToken en claro (solo para depuración puntual).
        // En producción debe quedar en false: se loguea un hash truncado.
        'log_raw_token'    => env('ALIANZA_LOG_RAW_TOKEN', false),
    ],

];
