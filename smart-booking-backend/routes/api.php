<?php
use App\Http\Controllers\Api\WorkRuleController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\ClientBookingController;


Route::get('/services', [ServiceController::class, 'index']);

Route::get('/availability', [AvailabilityController::class, 'index']);

Route::post('/client-bookings', [ClientBookingController::class, 'store']);



Route::apiResource('work-rules', WorkRuleController::class)
    ->only(['index', 'store', 'update', 'destroy']);
