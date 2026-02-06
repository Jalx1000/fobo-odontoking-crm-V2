<?php

use Illuminate\Support\Facades\Route;
use Webkul\Doctor\Http\Controllers\Api\AvailabilityController;

Route::prefix('api')->group(function () {
    Route::get('doctors/{doctorId}/availability/{year}/{month}', [AvailabilityController::class, 'getForMonth'])
        ->name('api.doctors.availability');
});
