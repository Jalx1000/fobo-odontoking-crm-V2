<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    | Credentials for Meta's WhatsApp Cloud API. Wired in Sprint 0/2 backend.
    */
    'cloud' => [
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'token'           => env('WHATSAPP_TOKEN'),
        'app_secret'      => env('WHATSAPP_APP_SECRET'),
        'verify_token'    => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version'     => env('WHATSAPP_API_VERSION', 'v21.0'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | Dedicated queue (Redis) for inbound ingestion and outbound sending jobs.
    */
    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),

    /*
    |--------------------------------------------------------------------------
    | AI Agent
    |--------------------------------------------------------------------------
    | Global default for the AI agent switch and the outbound webhook the
    | external agent (n8n or other) is notified on. Overridable per conversation.
    */
    'ai' => [
        'enabled'      => env('WHATSAPP_AI_ENABLED', false),
        'webhook_url'  => env('WHATSAPP_AGENT_WEBHOOK_URL'),
        'history_size' => env('WHATSAPP_AGENT_HISTORY_SIZE', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Front-end
    |--------------------------------------------------------------------------
    | While the backend endpoints are being built (Sprints 0-2) the inbox
    | renders mock data so the UI can be reviewed. Flip to false once the real
    | endpoints/broadcasting are wired.
    */
    'ui' => [
        'use_mock' => env('WHATSAPP_UI_USE_MOCK', false),
    ],
];
