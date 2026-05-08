<?php

use Illuminate\Support\Facades\Route;
use Webkul\Admin\Http\Controllers\Api\InsuranceController;

Route::prefix('api')->group(function () {
    Route::post('insurance/verify', [InsuranceController::class, 'verify'])->name('api.insurance.verify');
});
