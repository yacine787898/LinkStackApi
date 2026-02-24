<?php

use App\Http\Controllers\Api\ProvisioningController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware(['provision.token', 'throttle:provisioning'])->group(function () {
    Route::post('/provision/accounts', [ProvisioningController::class, 'store']);
});
