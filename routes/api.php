<?php

use HassanDomeDenea\HddLaravelHelpers\Controllers\BatchRequestController;
use HassanDomeDenea\HddLaravelHelpers\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {

    Route::post('batch-request', BatchRequestController::class)->name('batch-request');

    Route::post('batch-request/{name}/{age?}', BatchRequestController::class)->name('batch-request2');

    Route::patch('medias/{media}/update-date', [MediaController::class, 'updateDate'])->name('media.update-date')
        ->middleware('auth:sanctum');

    Route::patch('medias/{media}/update-description', [MediaController::class, 'updateDescription'])->name('media.update-date')
        ->middleware('auth:sanctum');

    Route::patch('medias/{media}/manipulate', [MediaController::class, 'manipulate'])->name('media.manipulate')
        ->middleware('auth:sanctum');


});
