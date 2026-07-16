<?php

use Illuminate\Support\Facades\Route;
use Webkul\Whatsapp\Http\Controllers\InboxController;

/*
|--------------------------------------------------------------------------
| WhatsApp admin (authenticated) routes
|--------------------------------------------------------------------------
| Consumed by the inbox component inside the CRM. Registered under the admin
| path with the web + user middleware in the service provider.
*/

Route::controller(InboxController::class)
    ->prefix('whatsapp')
    ->name('admin.whatsapp.')
    ->group(function () {
        Route::get('thread', 'thread')->name('thread');
        Route::post('send', 'send')->name('send');
        Route::patch('conversations/{id}/agent', 'toggleAgent')->name('agent');
    });
