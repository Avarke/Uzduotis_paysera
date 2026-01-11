<?php
use App\Http\Controllers\Api\WorkRuleController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['pong' => true]));


Route::apiResource('work-rules', WorkRuleController::class)
    ->only(['index', 'store', 'update', 'destroy']);
