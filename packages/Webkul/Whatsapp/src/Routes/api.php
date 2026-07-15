<?php

use Illuminate\Support\Facades\Route;
use Webkul\Whatsapp\Http\Controllers\WebhookController;

/*
|--------------------------------------------------------------------------
| WhatsApp API routes
|--------------------------------------------------------------------------
| Public webhook endpoints consumed by Meta's WhatsApp Cloud API. They live
| outside the "web" middleware group, so no CSRF/session applies. Signature
| validation happens inside the controller.
*/

Route::prefix('api/v1/whatsapp')->group(function () {
    Route::get('webhook', [WebhookController::class, 'verify'])->name('whatsapp.webhook.verify');
    Route::post('webhook', [WebhookController::class, 'receive'])->name('whatsapp.webhook.receive');
});
