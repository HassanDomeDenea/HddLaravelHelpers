<?php

use HassanDomeDenea\HddLaravelHelpers\Controllers\BatchRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {
    Route::post('batch-request', BatchRequestController::class)->name('batch-request');
    Route::post('batch-request/{name}/{age?}', BatchRequestController::class)->name('batch-request2');
});
