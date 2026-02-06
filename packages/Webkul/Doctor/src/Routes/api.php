<?php

use Illuminate\Support\Facades\Route;
use Webkul\Doctor\Http\Controllers\Api\AvailabilityController;
use Webkul\Doctor\Http\Controllers\Api\DoctorController;

Route::prefix('api')->group(function () {
    Route::get('doctors', [DoctorController::class, 'index'])->name('api.doctors.index');
    Route::get('doctors/{id}', [DoctorController::class, 'show'])->name('api.doctors.show');
    Route::get('doctors/{doctorId}/availability/{year}/{month}', [AvailabilityController::class, 'getForMonth'])
        ->name('api.doctors.availability');
});
