<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Api\InsuranceController;
use Webkul\Admin\Http\Controllers\Api\PersonController;

Route::prefix('api')->group(function () {
    Route::post('insurance/verify', [InsuranceController::class, 'verify'])->name('api.insurance.verify');

    Route::prefix('v1')->group(function () {
        Route::get('insurance/verify', [InsuranceController::class, 'verifyForAgent'])
            ->middleware('throttle:insurance-agent')
            ->name('api.v1.insurance.verify');

        Route::get('persons', [PersonController::class, 'index'])->name('api.v1.persons.index');
    });
});
