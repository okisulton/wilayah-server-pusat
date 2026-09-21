<?php

use App\Http\Controllers\Api\WilayahApiController;
use App\Http\Middleware\VerifyPuskesmasToken;
use Illuminate\Support\Facades\Route;

Route::get('/health', [WilayahApiController::class, 'healthCheck']);

Route::prefix('v1/wilayah')->middleware(VerifyPuskesmasToken::class)->group(function () {
    Route::get('/setup-district/{code}', [WilayahApiController::class, 'getDistrictSetup']);
});