<?php

use HassanDomeDenea\HddLaravelHelpers\Controllers\AuditController;
use HassanDomeDenea\HddLaravelHelpers\Controllers\BatchRequestController;
use HassanDomeDenea\HddLaravelHelpers\Controllers\MediaController;
use Illuminate\Support\Facades\Route;

Route::prefix('api')->group(function () {

    Route::post('batch-request', BatchRequestController::class)->name('batch-request');

    Route::post('batch-request/{name}/{age?}', BatchRequestController::class)->name('batch-request2');

    Route::patch('medias/{media}/update-date', [MediaController::class, 'updateDate'])->name('media.update-date')
        ->middleware('auth:sanctum');

    Route::patch('medias/{media}/update-description', [MediaController::class, 'updateDescription'])->name('media.update-description')
        ->middleware('auth:sanctum');

    Route::patch('medias/{media}/manipulate', [MediaController::class, 'manipulate'])->name('media.manipulate')
        ->middleware('auth:sanctum');

    Route::delete('medias/{media}', [MediaController::class, 'destroy'])->name('media.destroy')
        ->middleware('auth:sanctum');

    Route::delete('medias/{media}', [MediaController::class, 'destroy'])->name('media.destroy')
        ->middleware('auth:sanctum');

    Route::get('audits', [AuditController::class, 'index'])->name('audits.index')
        ->middleware('auth:sanctum');

});
